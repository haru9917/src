<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;
use Cake\I18n\FrozenDate;
use Cake\ORM\TableRegistry;

/**
 * Sc02 用の発注サマリー取得サービス
 *
 * このクラスはコントローラから呼び出され、複数の Table を使って
 * 発注状況の集計（合計、発注済、未済、未済店番リストの整形）を行います。
 * コントローラは本サービスを呼ぶだけにし、ビジネスロジックはここに集約します。
 */
class Sc02OrderSummaryService
{
    /**
     * コンストラクタ
     * 依存する Table は外部注入がなければ TableLocator から取得します。
     *
     * @param \App\Model\Table\MLocationsTable|null $MLocations
     * @param \App\Model\Table\MCentersTable|null $MCenters
     * @param \App\Model\Table\TStoreOrdersTable|null $TStoreOrders
     * @param \App\Model\Table\TOrderControlsTable|null $TOrderControls
     */
    public function __construct(
        protected ?\App\Model\Table\MLocationsTable $MLocations = null,
        protected ?\App\Model\Table\MCentersTable $MCenters = null,
        protected ?\App\Model\Table\TStoreOrdersTable $TStoreOrders = null,
        protected ?\App\Model\Table\TOrderControlsTable $TOrderControls = null
    ) {
        $locator = TableRegistry::getTableLocator();
        $this->MLocations = $this->MLocations ?: $locator->get('MLocations');
        $this->MCenters = $this->MCenters ?: $locator->get('MCenters');
        $this->TStoreOrders = $this->TStoreOrders ?: $locator->get('TStoreOrders');
        $this->TOrderControls = $this->TOrderControls ?: $locator->get('TOrderControls');
    }

    /**
     * サマリーを取得してコントローラへ渡す配列を返す
     *
     * 処理の大まかな流れ:
     *  1) 営業日を TOrderControls から取得（存在しなければ今日の日付）
     *  2) 表示用の日付フォーマットを作成
     *  3) 中心（エリア）一覧を取得
     *  4) 対象店舗を MLocations から取得（disable_flg と本部除外）
     *  5) 当日の発注済店番を TStoreOrders から取得（distinct）
     *  6) 未済店番を差分で計算し、所定件数ごとに改行して整形
     *
     * @param int|null $centerId センターID（未指定時はデフォルトを利用）
     * @param int|null $locationId ロケーションID（未使用だがコントローラ互換のため保持）
     * @param int $perRow 未済リストを何件ごとに改行するか（デフォルト 10）
     * @return array コントローラへ渡す配列（businessDateDb, businessDateView, centers, center_id, location_id, summary）
     */
    public function getSummary(?int $centerId, ?int $locationId, int $perRow = 10): array
    {
    // ※ 営業日は対象センター（controlCenterId）ごとの最新レコードを使う

        // 3) エリアプルダウン用データ
        $centers = $this->MCenters->getCenterOptions();

        // 4) 対象店舗取得（m_locations）
        $locationsQuery = $this->MLocations->find()
            ->where([
                'disable_flg' => 0,
                'no !=' => 1, // 本部除外
            ]);

    // control 用に中心 ID を決定する (優先順: 引数の $centerId -> locationId から解決 -> デフォルト)
        $controlCenterId = null;
        if (!empty($centerId)) {
            $controlCenterId = $centerId;
        } elseif ($locationId !== null) {
            $loc = $this->MLocations->find()
                ->select(['center_id'])
                ->where(['id' => $locationId])
                ->first();
            if ($loc) {
                $controlCenterId = $loc->center_id;
            }
        }

        if (!empty($centerId)) {
            $locationsQuery->where(['center_id' => (int)$centerId]);
        } else {
            // デフォルト値 (広島センター)
            $centerId = 341;
            // まだ controlCenterId が無ければデフォルトを使う
            $controlCenterId = $controlCenterId ?? $centerId;
        }

        // 1) 営業日 --- 指定センターの最新 TOrderControls レコードから取得
        $orderControl = null;
        $orderControlId = null;
        $orderFlagFromControl = null;
        if ($controlCenterId !== null) {
            $orderControl = $this->TOrderControls->find()
                ->where(['center_id' => (string)$controlCenterId])
                ->order(['business_date' => 'DESC'])
                ->first();
            if ($orderControl) {
                $orderControlId = $orderControl->id ?? null;
                $orderFlagFromControl = isset($orderControl->order_flag) ? (string)$orderControl->order_flag : null;
            }
        }

        // FrozenDate が返ることがあるため string に整形。見つからなければ今日
        if ($orderControl && $orderControl->business_date instanceof FrozenDate) {
            $businessDateDb = $orderControl->business_date->format('Y-m-d');
        } else {
            $businessDateDb = date('Y-m-d');
        }

        // 2) 表示用フォーマット（例: 2025年11月18日(火) のようにする）
        $w = ['日','月','火','水','木','金','土'];
        $ts = strtotime($businessDateDb);
        $businessDateView = date('Y年m月d日', $ts) . '(' . $w[date('w', $ts)] . ')';

        $locations = $locationsQuery->enableHydration(false)->all();

        // 店番リスト（tencd と紐づくのは no）
        $storeNos = array_column($locations->toArray(), 'no');
        $locationCount = count($storeNos);

        // 5) 発注済店舗 --- TStoreOrders から取得
        $orderedNos = [];
        if ($locationCount > 0) {
            $orderedNos = $this->TStoreOrders->find()
                ->select(['tencd'])
                ->where([
                    'hatymd' => $businessDateDb,
                    'tencd IN' => $storeNos
                ])
                ->distinct()
                ->enableHydration(false)
                ->extract('tencd')
                ->toArray();
        }

        $orderedCount = count($orderedNos);

        // 6) 未済店番 --- 差分を取り、ソートして整形
        $pendingNos = array_diff($storeNos, $orderedNos);
        sort($pendingNos);

        $pendingList = $this->formatPendingList($pendingNos, $perRow);

        $summary = [
            'total' => $locationCount,
            'completed' => $orderedCount,
            'pending' => count($pendingNos),
            'pending_list' => $pendingList,
        ];

    // order_flag は可能なら上で取得した t_order_controls レコードの id を使って取得する
    $orderFlag = $orderFlagFromControl ?? $this->getOrderFlag(null, $centerId, $locationId);

        // コントローラに渡す形で返す
        return [
            'businessDateDb' => $businessDateDb,
            'businessDateView' => $businessDateView,
            'centers' => $centers,
            'center_id' => $centerId,
            'location_id' => $locationId,
            'summary' => $summary,
            // テンプレートで使用するために orderFlag を返す
            'orderFlag' => $orderFlag,
            // 取得した t_order_controls の id を返す（存在しない場合は null）
            'order_control_id' => $orderControlId ?? null,
        ];
    }

    /**
     * 指定の centerId または locationId から t_order_controls の order_flag を取得する
     *
     * @param int|null $centerId
     * @param int|null $locationId
     * @return string|null '0' または '1' など文字列で返す。見つからなければ null
     */
    /**
     * order_control の id が与えられればそれを優先して order_flag を取得する。
     * orderControlId が無ければ centerId/locationId から解決する従来の動作にフォールバックする。
     *
     * @param int|null $orderControlId t_order_controls.id を直接指定する
     * @param int|null $centerId
     * @param int|null $locationId
     * @return string|null
     */
    public function getOrderFlag(?int $orderControlId = null, ?int $centerId = null, ?int $locationId = null): ?string
    {
        // orderControlId が指定されていればそれを使う
        if ($orderControlId !== null) {
            $orderControl = $this->TOrderControls->find()
                ->where(['id' => (int)$orderControlId])
                ->select(['order_flag'])
                ->first();
            if ($orderControl) {
                return (string)$orderControl->order_flag;
            }
            return null;
        }

        // フォールバック: centerId または locationId から controlCenterId を解決
        $controlCenterId = null;
        if (!empty($centerId)) {
            $controlCenterId = $centerId;
        } elseif ($locationId !== null) {
            $loc = $this->MLocations->find()
                ->select(['center_id'])
                ->where(['id' => $locationId])
                ->first();
            if ($loc) {
                $controlCenterId = $loc->center_id;
            }
        }

        if ($controlCenterId !== null) {
            $orderControl = $this->TOrderControls->find()
                ->where(['center_id' => (string)$controlCenterId])
                ->select(['order_flag'])
                ->first();
            if ($orderControl) {
                return (string)$orderControl->order_flag;
            }
        }
        return null;
    }

    /**
     * 未済店番の配列を受け取り、指定件数ごとにカンマ区切りかつ改行（<br>）で整形して返す
     * 空配列の場合は '-' を返す
     *
     * @param array $pendingNos 未済店番の配列
     * @param int $perRow 何件ごとに改行するか
     * @return string 整形済み文字列
     */
    protected function formatPendingList(array $pendingNos, int $perRow): string
    {
        if (empty($pendingNos)) {
            return '-';
        }
        $chunks = array_chunk($pendingNos, $perRow);
        $pendingFormatted = [];
        foreach ($chunks as $group) {
            // 各グループはカンマ区切りで連結
            $pendingFormatted[] = implode(',', $group);
        }
        // グループ間は <br> で改行
        return implode('<br>', $pendingFormatted);
    }

    /**
     * 過去データダウンロード用の CSV を生成して返す
     * 取得元は t_store_orders。商品名は manso_wms.m_products から補完する。
     *
     * @param string $businessDate 日付入力欄の YYYY-MM-DD
     * @param int|null $centerId 画面のセンター選択値
     * @param int|null $locationId ロケーション ID（センター解決のフォールバック）
     * @return array{csv: string, business_date: string, order_control_id: string|null}
     */
    public function exportPastCsv(string $businessDate, ?int $centerId = null, ?int $locationId = null): array
    {
        $businessDate = trim($businessDate);

        // center_id は指定 > location から解決 > デフォルト(広島センター=341)
        $controlCenterId = null;
        if (!empty($centerId)) {
            $controlCenterId = $centerId;
        } elseif ($locationId !== null) {
            $loc = $this->MLocations->find()
                ->select(['center_id'])
                ->where(['id' => $locationId])
                ->first();
            if ($loc) {
                $controlCenterId = $loc->center_id;
            }
        }
        if ($controlCenterId === null) {
            $controlCenterId = 341;
        }

        // 該当営業日の order_control_id を取得
        $orderControl = $this->TOrderControls->find()
            ->select(['id'])
            ->where([
                'business_date' => $businessDate,
                'center_id' => (string)$controlCenterId,
            ])
            ->order(['id' => 'DESC'])
            ->first();

        $orderControlId = $orderControl ? (string)$orderControl->id : null;
        if ($orderControlId === null) {
            return [
                'csv' => '',
                'business_date' => $businessDate,
                'order_control_id' => null,
            ];
        }

        $conn = $this->TStoreOrders->getConnection();
        $sql = <<<SQL
SELECT
    s.tencd AS area,
    s.shohincd,
    s.bin,
    s.hatymd,
    s.cases,
    s.cases2,
    s.cases3
FROM t_store_orders s
WHERE s.order_control_id = :id
ORDER BY s.tencd, s.shohincd, s.bin
SQL;
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $orderControlId]);
        $rows = $stmt->fetchAll('assoc');

        // 商品名を別接続（manso_wms）から取得
        $productNames = [];
        if (!empty($rows)) {
            $codes = array_values(array_unique(array_column($rows, 'shohincd')));
            if (!empty($codes)) {
                $placeholders = implode(', ', array_fill(0, count($codes), '?'));
                $wmsConn = ConnectionManager::get('wms');
                $nameSql = sprintf(
                    'SELECT cd, name FROM m_products WHERE cd IN (%s)',
                    $placeholders
                );
                $nameStmt = $wmsConn->prepare($nameSql);
                $nameStmt->execute($codes);
                $productNames = array_column($nameStmt->fetchAll('assoc'), 'name', 'cd');
            }
        }

        $header = ['area', 'shohincd', 'hinmei', 'bin', 'hatymd', 'cases', 'cases2', 'cases3'];

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $header);
        foreach ($rows as $row) {
            $line = [
                $row['area'] ?? '',
                $row['shohincd'] ?? '',
                $productNames[$row['shohincd']] ?? '',
                $row['bin'] ?? '',
                $row['hatymd'] ?? '',
                $row['cases'] ?? '',
                $row['cases2'] ?? '',
                $row['cases3'] ?? '',
            ];
            fputcsv($fh, $line);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return [
            'csv' => $csv,
            'business_date' => $businessDate,
            'order_control_id' => $orderControlId,
        ];
    }

    /**
     * 指定の order_control_id に基づいて CSV を生成して文字列で返す
     * レイアウトは t_demand_rows のカラム（order_control_id を除く）。
     * t_store_orders に存在するカラム（cases, cases2, cases3, zaiko）は優先してそちらの値を使う。
     *
     * @param string $orderControlId
     * @return string CSV 文字列（UTF-8）
     */
    /**
     * 指定の order_control_id に対して CSV を生成して返します。
     * 戻り値は配列で 'csv' => CSV文字列, 'business_date' => 'YYYY-MM-DD' を含みます。
     *
     * @param string $orderControlId
     * @return array{csv: string, business_date: string}
     */
    public function exportCsv(string $orderControlId): array
    {
        $conn = $this->TOrderControls->getConnection();

        // SELECT カラム定義（t_demand_rows のカラム。order_control_id は除く）
        $cols = [
            "d.tencd",
            "d.shohincd",
            "d.hinmei",
            "d.bumoncd",
            "d.hatread",
            "d.irisu",
            "d.yoso",
            "d.heikinhanbaisu",
            "d.jhansu1",
            "d.jhansu2",
            "d.jhansu3",
            "d.nyukasu1",
            "d.nyukasu2",
            "d.nyukasu3",
            "d.hatymd",
            "d.bara",
            // cases 系列は t_store_orders を優先
            "COALESCE(s.cases, d.cases) AS cases",
            "d.gyo",
            "d.bin",
            "COALESCE(s.zaiko, d.zaiko) AS zaiko",
            "d.hansu1",
            "d.hansu2",
            "d.hansu3",
            "d.hansu4",
            "d.midashi",
            "d.bara2",
            "COALESCE(s.cases2, d.cases2) AS cases2",
            "d.tsuikabin",
            "d.bara3",
            "COALESCE(s.cases3, d.cases3) AS cases3",
            "d.doshinkbn",
            "d.tenpokbn",
            "d.nyukasu0",
        ];

        $sql = sprintf(
            "SELECT %s FROM t_demand_rows d LEFT JOIN t_store_orders s ON d.tencd = s.tencd AND d.shohincd = s.shohincd AND d.bin = s.bin AND d.hatymd = s.hatymd AND s.order_control_id = :id WHERE d.order_control_id = :id ORDER BY d.tencd, d.shohincd, d.bin",
            implode(', ', $cols)
        );

        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $orderControlId]);
        $rows = $stmt->fetchAll('assoc');

        // ヘッダは SELECT の各定義から AS 名またはカラム名を抽出
        $header = array_map(function ($c) {
            if (preg_match('/\s+AS\s+(\w+)$/i', $c, $m)) {
                return $m[1];
            }
            if (preg_match('/\.([a-zA-Z0-9_]+)$/', $c, $m)) {
                return $m[1];
            }
            return $c;
        }, $cols);

        // CSV をメモリに生成
        $fh = fopen('php://temp', 'r+');
        // ヘッダ書き込み
        fputcsv($fh, $header);
        foreach ($rows as $r) {
            $line = [];
            foreach ($header as $h) {
                $line[] = $r[$h] ?? '';
            }
            fputcsv($fh, $line);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        // business_date を t_order_controls から取得
        $orderControl = TableRegistry::getTableLocator()->get('TOrderControls')->find()
            ->select(['business_date'])
            ->where(['id' => $orderControlId])
            ->first();
        $businessDate = null;
        if ($orderControl) {
            $bd = $orderControl->business_date;
            if ($bd instanceof FrozenDate) {
                $businessDate = $bd->format('Y-m-d');
            } else {
                $businessDate = (string)$bd;
            }
        } else {
            $businessDate = date('Y-m-d');
        }

        return ['csv' => $csv, 'business_date' => $businessDate];
    }
}
