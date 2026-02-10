<?php

function renderActionButtons(int $id, string $titleEdit='Bearbeiten', string $titleDel='Löschen'): string {
  return '
  <div class="btn-group btn-group-sm action-group" role="group" aria-label="Aktionen">
    <button type="button" class="btn btn-outline-secondary btn-action-edit" data-id="'.$id.'" title="'.$titleEdit.'">
      <i class="bi bi-pencil-square"></i>
    </button>
    <button type="button" class="btn btn-outline-danger btn-action-delete" data-id="'.$id.'" title="'.$titleDel.'">
      <i class="bi bi-trash"></i>
    </button>
  </div>';
}
