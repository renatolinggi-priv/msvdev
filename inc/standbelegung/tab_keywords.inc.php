<?php
/** Tab «Art-Erkennung» – erwartet $artKeywords */
$groupedKeywords = [];
foreach ($artKeywords as $kw) $groupedKeywords[$kw['Art']][] = $kw;
?>
<div class="tab-pane fade" id="settings" role="tabpanel">
    <div class="table-wrapper">
        <h5 class="table-title"><span><i class="bi bi-tags me-2"></i>Art-Keywords</span></h5>
        <div class="p-3">
            <p class="text-muted small">
                Begriffe, die beim Export automatisch einer Art zugeordnet werden. Enthält eine Bezeichnung einen
                dieser Begriffe, wird die Art vorgeschlagen. Keywords haben Vorrang vor der Standard-Erkennung.
            </p>

            <div class="row g-2 mb-4 align-items-end">
                <div class="col-md-5">
                    <label class="visually-hidden" for="newKeyword">Neues Keyword</label>
                    <input type="text" class="form-control form-control-sm" id="newKeyword" placeholder="Neues Keyword (z.B. Schlossturmschiessen)">
                </div>
                <div class="col-md-4">
                    <label class="visually-hidden" for="newKeywordArt">Art</label>
                    <select class="form-select form-select-sm" id="newKeywordArt">
                        <?php foreach (SB_ART_CODES as $code => $label): ?>
                        <option value="<?= $code ?>"><?= $code ?> – <?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-outline-success btn-sm w-100" data-action="keyword-add"><i class="bi bi-plus-lg me-1"></i>Hinzufügen</button>
                </div>
            </div>

            <div id="keywordsList">
                <?php foreach (SB_ART_CODES as $code => $label): $keywords = $groupedKeywords[$code] ?? []; ?>
                <div class="mb-3">
                    <h6 class="mb-1">
                        <span class="badge badge-<?= $code ?>"><?= $code ?></span> <?= $label ?>
                        <small class="text-muted kw-count" data-art="<?= $code ?>">(<?= count($keywords) ?>)</small>
                        <?php if (!empty(SB_ART_RULES[$code])): ?>
                        <small class="text-muted ms-2" data-tooltip="Standard-Erkennung ohne Keyword"><i class="bi bi-magic me-1"></i><?= htmlspecialchars(implode(', ', SB_ART_RULES[$code])) ?></small>
                        <?php endif; ?>
                    </h6>
                    <div class="keywords-container" data-art="<?= $code ?>">
                        <?php if (!$keywords): ?>
                        <span class="text-muted fst-italic small kw-empty">Keine Keywords definiert</span>
                        <?php else: foreach ($keywords as $kw): ?>
                        <span class="keyword-tag" data-id="<?= (int)$kw['ID'] ?>">
                            <?= htmlspecialchars($kw['Keyword']) ?>
                            <button type="button" class="btn-remove" data-action="keyword-delete" data-id="<?= (int)$kw['ID'] ?>" aria-label="Keyword entfernen">&times;</button>
                        </span>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
