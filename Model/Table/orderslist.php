<?php
/**
 * @var \App\View\AppView $this
 * @var object[] $demandRows
 * @var int[] $storeIds
 * @var array $storeOrdersByProduct
 * @var int[] $productRowCounts
 */
?>
<h1>発注リスト</h1>

<table>
    <thead>
        <tr>
            <th>商品名</th>
            <th>入数</th>
            <th>出荷便</th>
            <th>発注総数</th>
            <?php foreach ($storeIds as $storeId): ?>
                <th><?= h($storeId) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php
        $lastHinmei = null; // 直前に表示した商品名を保持する変数
        ?>
        <?php foreach ($demandRows as $demandRow): ?>
            <tr>
                <?php if ($demandRow->hinmei !== $lastHinmei): ?>
                    <td rowspan="<?= h($productRowCounts[$demandRow->hinmei] ?? 1) ?>"><?= h($demandRow->hinmei) ?></td>
                    <td rowspan="<?= h($productRowCounts[$demandRow->hinmei] ?? 1) ?>"><?= h($demandRow->irisu) ?></td>
                    <?php $lastHinmei = $demandRow->hinmei; ?>
                <?php endif; ?>

                <td><?= h($demandRow->shipping_flight_number) ?></td>
                <td><?= h($demandRow->total_order_qty) ?></td>

                <?php foreach ($storeIds as $storeId): ?>
                    <td>
                        <?= h($storeOrdersByProduct[$demandRow->id][$storeId] ?? '') ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>