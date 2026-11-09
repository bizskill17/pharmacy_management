<?php
/** Photo 1 to 5, each opening after the one before it. Expects $existing: slot number => photo address. */
$existing = $existing ?? [];
$svg = fn (string $paths, int $size) => "<svg width=\"$size\" height=\"$size\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" aria-hidden=\"true\">$paths</svg>";
$uploadIcon = $svg('<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>', 22);
$cameraIcon = $svg('<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>', 22);
$trashIcon = $svg('<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>', 20);
?>
<div class="photo-group pin-photos">
  <h3 class="photo-head<?= !empty($photoRequired) ? ' req' : '' ?>">Photos</h3>
  <div class="pin-slots">
    <?php for ($slot = 1; $slot <= PURCHASE_IN_PHOTOS; $slot++): $url = $existing[$slot] ?? null; ?>
    <div class="pin-slot" data-pin-slot>
      <span class="slot-name">Photo <?= $slot ?></span>
      <?php if ($url): ?><input type="hidden" name="keep_<?= $slot ?>" value="1" data-pin-keep><?php endif; ?>
      <input class="sr-input" type="file" name="photo<?= $slot ?>" accept="image/jpeg,image/png,image/webp" data-compress tabindex="-1" aria-label="Upload Photo <?= $slot ?>">
      <input class="sr-input" type="file" name="photo<?= $slot ?>_cam" accept="image/*" capture="environment" data-compress tabindex="-1" aria-label="Click Photo <?= $slot ?>">
      <div class="slot-actions" data-pin-actions>
        <button type="button" class="slot-pick" data-pin-pick="0" aria-label="Upload Photo <?= $slot ?>" title="Upload photo"><?= $uploadIcon ?></button>
        <button type="button" class="slot-pick main" data-pin-pick="1" aria-label="Click Photo <?= $slot ?>" title="Click photo"><?= $cameraIcon ?></button>
      </div>
      <img class="pin-preview" alt="Photo <?= $slot ?> preview"<?= $url ? ' src="' . e($url) . '"' : '' ?>>
      <button type="button" class="slot-remove" data-pin-remove aria-label="Remove Photo <?= $slot ?>" title="Remove"><?= $trashIcon ?></button>
    </div>
    <?php endfor; ?>
  </div>
</div>
