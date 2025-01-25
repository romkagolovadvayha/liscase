<ul class="nav nav-tabs" id="myTab" role="tablist">
    <?php foreach ($tabs as $i => $item): ?>
        <li class="nav-item" role="presentation">
            <button
                class="nav-link<?=$i === 0 ? ' active' : ''?>"
                id="form-tab-<?=$i?>"
                data-bs-toggle="tab"
                data-bs-target="#form-<?=$i?>"
                type="button"
                role="tab"
                aria-controls="form-<?=$i?>"
                aria-selected="<?=$i === 0 ? 'true' : 'false'?>">
                <?=$item['title']?>
            </button>
        </li>
    <?php endforeach; ?>
</ul>
<div class="tab-content">
    <?php foreach ($tabs as $i => $item): ?>
        <div class="tab-pane<?=$i === 0 ? ' active' : ''?>" id="form-<?=$i?>" role="tabpanel" aria-labelledby="form-tab-<?=$i?>">
            <div class="setting">
                <?php if (empty($item['items'])): ?>
                    <?=$this->render('form', $item)?>
                <?php else: ?>
                    <?php foreach ($item['items'] as $_item): ?>
                        <?=$this->render('form', $_item)?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>