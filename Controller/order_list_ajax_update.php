<?php
/**
 * @var \App\View\AppView $this
 * @var int[] $storeIds
 */
?>
<thead>
    <tr>
        <th class="red-header product-col sticky-col" rowspan="2" style="position: -webkit-sticky; position: sticky; z-index: 2;">商品</th>
        <th class="red-header qty-col sticky-col" rowspan="2" style="position: -webkit-sticky; position: sticky; z-index: 2;">入数</th>
        <th class="red-header ship-col sticky-col" rowspan="2" style="position: -webkit-sticky; position: sticky; z-index: 2;">出荷便</th>
        <th class="red-header total-col sticky-col" rowspan="2" style="position: -webkit-sticky; position: sticky; z-index: 2;">発注総数</th>
        <th class="red-header" colspan="<?= count($storeIds) ?>" style="padding-bottom: 0;">店番</th>
    </tr>
    <tr>
        <?php foreach ($storeIds as $storeId): ?>
            <th class="red-header store-col" style="padding-top: 0; padding-bottom: 8px;"><?= h($storeId) ?></th>
        <?php endforeach; ?>
    </tr>
</thead>
<?php include(ROOT . '/templates/Sc03/order_list_body.php'); ?>