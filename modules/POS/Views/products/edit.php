<?php
declare(strict_types=1);

/**
 * Products → Edit
 * Inputs: $base, $prod, and usually:
 *   $categories OR $cats, $brands, $uoms, $taxes, $suppliers
 */

$h = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');

$old = $_SESSION['pos_old']    ?? [];
$err = $_SESSION['pos_errors'] ?? [];
unset($_SESSION['pos_old'], $_SESSION['pos_errors']);

$val = function(string $key, $fallback = '') use ($old, $prod) {
    return $old[$key] ?? ($prod[$key] ?? $fallback);
};

$id    = (int)($prod['id'] ?? 0);
$brand = '#228B22';

/* ----- gracefully handle different variable names ----- */
$categories = $categories ?? ($cats ?? []);
$brands     = $brands     ?? [];
$uoms       = $uoms       ?? [];
$taxes      = $taxes      ?? [];
$suppliers  = $suppliers  ?? [];

/* ----- detect stored image path (if any) ----- */
$imagePath = '';
foreach (['image_path','image','photo','picture'] as $col) {
    if (!empty($prod[$col])) {
        $imagePath = (string)$prod[$col];
        break;
    }
}
$imgUrl = $imagePath !== '' ? '/modules/POS/Assets/products/'.$imagePath : '';
?>
<style>
  .pos-edit-page{
    width:100%;
    max-width:1200px;
    margin:0 auto;
    padding:16px 24px 32px;
    box-sizing:border-box;
    font-size:14px;
  }
  .pos-edit-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    margin-bottom:16px;
  }
  .pos-title{
    font-size:22px;
    font-weight:600;
    margin:0;
  }
  .pos-subtitle{
    font-size:12px;
    color:#6b7280;
    margin-top:4px;
  }
  .pos-back-btn{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 10px;
    border-radius:8px;
    border:1px solid #d1d5db;
    background:#fff;
    color:#111827;
    text-decoration:none;
    font-size:13px;
  }
  .pos-back-btn:hover{background:#f3f4f6;}

  .pos-grid-two{
    display:grid;
    grid-template-columns:minmax(0,2.2fr) minmax(0,1fr);
    gap:20px;
    align-items:flex-start;
  }
  @media (max-width:900px){
    .pos-grid-two{grid-template-columns:1fr;}
  }

  .pos-card{
    border:1px solid #e5e7eb;
    border-radius:12px;
    background:#ffffff;
    padding:18px 20px;
    box-shadow:0 1px 2px rgba(15,23,42,0.05);
    box-sizing:border-box;
  }
  .pos-card-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:12px;
  }
  .pos-card-title{
    font-size:11px;
    text-transform:uppercase;
    font-weight:600;
    letter-spacing:0.06em;
    color:#4b5563;
  }
  .pos-card-small{
    font-size:11px;
    color:#9ca3af;
  }

  .pos-card-stack{
    display:flex;
    flex-direction:column;
    gap:16px;
  }
  .pos-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:14px;
  }
  @media (max-width:640px){
    .pos-grid{grid-template-columns:1fr;}
  }

  .pos-label{
    display:block;
    font-size:11px;
    font-weight:600;
    color:#4b5563;
    margin-bottom:4px;
  }
  .pos-input,
  .pos-select{
    width:100%;
    box-sizing:border-box;
    border-radius:8px;
    border:1px solid #d1d5db;
    padding:7px 9px;
    font-size:13px;
    outline:none;
    background:#ffffff;
  }
  .pos-input:focus,
  .pos-select:focus{
    border-color:#10b981;
    box-shadow:0 0 0 1px #10b98133;
  }
  .pos-error{
    margin-top:4px;
    font-size:11px;
    color:#b91c1c;
  }

  .pos-checkbox-row{
    display:flex;
    align-items:center;
    gap:14px;
    margin-top:4px;
  }
  .pos-check-label{
    display:inline-flex;
    align-items:center;
    gap:6px;
    font-size:13px;
    color:#374151;
  }

  .pos-image-placeholder{
    display:flex;
    align-items:center;
    justify-content:center;
    height:110px;
    border-radius:10px;
    border:1px dashed #d1d5db;
    color:#9ca3af;
    font-size:12px;
    text-align:center;
    padding:0 10px;
    box-sizing:border-box;
  }
  .pos-image-preview{
    display:flex;
    align-items:center;
    gap:10px;
  }
  .pos-image-preview img{
    width:80px;
    height:80px;
    border-radius:10px;
    object-fit:cover;
    border:1px solid #e5e7eb;
  }
  .pos-help-text{
    font-size:11px;
    color:#9ca3af;
    margin-top:4px;
  }

  .pos-actions{
    margin-top:18px;
    border:1px solid #e5e7eb;
    border-radius:12px;
    background:#ffffff;
    padding:12px 16px;
    box-shadow:0 1px 2px rgba(15,23,42,0.05);
    display:flex;
    align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:10px;
    box-sizing:border-box;
  }
  .pos-actions-text{
    font-size:11px;
    color:#6b7280;
  }
  .pos-btn{
    border-radius:9px;
    border:1px solid transparent;
    padding:7px 14px;
    font-size:13px;
    cursor:pointer;
  }
  .pos-btn-secondary{
    border-color:#d1d5db;
    background:#ffffff;
    color:#111827;
  }
  .pos-btn-secondary:hover{background:#f3f4f6;}
  .pos-btn-primary{
    background:<?= $brand ?>;
    color:#ffffff;
    box-shadow:0 1px 2px rgba(16,185,129,0.35);
  }
  .pos-btn-primary:hover{
    filter:brightness(0.95);
    box-shadow:0 2px 5px rgba(16,185,129,0.45);
  }
</style>

<div class="pos-edit-page">
  <div class="pos-edit-header">
    <div>
      <h1 class="pos-title">Edit Product</h1>
      <p class="pos-subtitle">
        Update pricing, stock and details. Changes affect new sales immediately.
      </p>
    </div>
    <a href="<?= $base ?>/products" class="pos-back-btn">
      <span style="font-size:11px;">←</span>
      <span>Back to list</span>
    </a>
  </div>

  <form method="post"
      action="<?= $base ?>/products/<?= (int)$prod['id'] ?>"
      enctype="multipart/form-data">
    
    <div class="pos-grid-two">
      <!-- LEFT COLUMN -->
      <div class="pos-card pos-card-stack">

        <!-- BASIC INFORMATION -->
        <section class="pos-card-stack">
          <div class="pos-card-header">
            <div class="pos-card-title">Basic information</div>
            <div class="pos-card-small">#<?= $id ?></div>
          </div>

          <div class="pos-grid">
            <div>
              <label class="pos-label">SKU *</label>
              <input name="sku" class="pos-input" required
                     value="<?= $h($val('sku')) ?>">
              <?php if(isset($err['sku'])): ?>
                <div class="pos-error"><?= $h($err['sku']) ?></div>
              <?php endif; ?>
            </div>

            <div>
              <label class="pos-label">Barcode</label>
              <input name="barcode" class="pos-input"
                     value="<?= $h($val('barcode')) ?>">
            </div>
          </div>

          <div>
            <label class="pos-label">Product Name *</label>
            <input name="name" class="pos-input" required
                   value="<?= $h($val('name')) ?>">
            <?php if(isset($err['name'])): ?>
              <div class="pos-error"><?= $h($err['name']) ?></div>
            <?php endif; ?>
          </div>

          <div class="pos-grid">
            <div>
              <label class="pos-label">Category</label>
              <select name="category_id" class="pos-select">
                <option value="">— None —</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= (int)($c['id'] ?? 0) ?>"
                    <?= ((string)$val('category_id') === (string)($c['id'] ?? '')) ? 'selected' : '' ?>>
                    <?= $h($c['name'] ?? '') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="pos-label">Brand</label>
              <select name="brand_id" class="pos-select">
                <option value="">— None —</option>
                <?php foreach ($brands as $b): ?>
                  <option value="<?= (int)($b['id'] ?? 0) ?>"
                    <?= ((string)$val('brand_id') === (string)($b['id'] ?? '')) ? 'selected' : '' ?>>
                    <?= $h($b['name'] ?? '') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="pos-grid">
            <div>
              <label class="pos-label">Unit / UOM</label>
              <input name="unit" list="uom_list" class="pos-input"
                     value="<?= $h($val('unit','pcs')) ?>">
              <datalist id="uom_list">
                <?php foreach ($uoms as $u): ?>
                  <option value="<?= $h($u['name'] ?? $u['code'] ?? '') ?>"></option>
                <?php endforeach; ?>
              </datalist>
            </div>

            <div>
              <label class="pos-label">Tax Rate (%)</label>
              <select name="tax_rate" class="pos-select">
                <option value="0">No Tax</option>
                <?php foreach ($taxes as $t): ?>
                  <?php $rate = (float)($t['rate'] ?? 0); ?>
                  <option value="<?= $h($rate) ?>"
                    <?= ((string)$val('tax_rate','0') === (string)$rate) ? 'selected' : '' ?>>
                    <?= $h(($t['name'] ?? 'Tax').' ('.$rate.'%)') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </section>

        <!-- PRICING & INVENTORY -->
        <section class="pos-card-stack" style="margin-top:10px;">
          <div class="pos-card-header">
            <div class="pos-card-title">Pricing &amp; inventory</div>
          </div>

          <div class="pos-grid">
            <div>
              <label class="pos-label">Cost Price</label>
              <input type="number" step="0.01"
                     name="cost_price" class="pos-input"
                     value="<?= $h($val('cost_price','0')) ?>">
            </div>

            <div>
              <label class="pos-label">Sale Price *</label>
              <input type="number" step="0.01"
                     name="sale_price" class="pos-input" required
                     value="<?= $h($val('sale_price','0')) ?>">
              <?php if(isset($err['sale_price'])): ?>
                <div class="pos-error"><?= $h($err['sale_price']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div>
            <label class="pos-label">Low Stock Threshold</label>
            <input type="number" step="1"
                   name="low_stock_threshold" class="pos-input"
                   value="<?= $h($val('low_stock_threshold','0')) ?>">
          </div>

          <div class="pos-checkbox-row">
            <label class="pos-check-label">
              <input type="checkbox" name="track_stock"
                     <?= ((int)$val('track_stock',0)===1)?'checked':'' ?>>
              <span>Track stock</span>
            </label>
            <label class="pos-check-label">
              <input type="checkbox" name="is_active"
                     <?= ((int)$val('is_active',1)===1)?'checked':'' ?>>
              <span>Active</span>
            </label>
          </div>
        </section>

        <!-- SUPPLIERS -->
        <section class="pos-card-stack" style="margin-top:10px;">
          <div class="pos-card-header">
            <div class="pos-card-title">Suppliers</div>
          </div>

          <div>
            <label class="pos-label">Primary Supplier</label>
            <select name="primary_supplier_id" class="pos-select">
              <option value="">— None —</option>
              <?php foreach ($suppliers as $s): ?>
                <option value="<?= (int)($s['id'] ?? 0) ?>"
                  <?= ((string)$val('primary_supplier_id') === (string)($s['id'] ?? '')) ? 'selected' : '' ?>>
                  <?= $h($s['name'] ?? '') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="pos-label">Supplier</label>
            <select name="supplier_id" class="pos-select">
              <option value="">— None —</option>
              <?php foreach ($suppliers as $s): ?>
                <option value="<?= (int)($s['id'] ?? 0) ?>"
                  <?= ((string)$val('supplier_id') === (string)($s['id'] ?? '')) ? 'selected' : '' ?>>
                  <?= $h($s['name'] ?? '') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </section>

      </div>

      <!-- RIGHT COLUMN: IMAGE -->
      <div class="pos-card">
        <div class="pos-card-header">
          <div class="pos-card-title">Product image</div>
        </div>

        <?php if ($imgUrl): ?>
          <div class="pos-image-preview">
            <img src="<?= $h($imgUrl) ?>" alt="Product image">
            <div class="pos-help-text">
              Current image. Upload a new file to replace it.
            </div>
          </div>
        <?php else: ?>
          <div class="pos-image-placeholder">
            No image yet. Upload one below.
          </div>
        <?php endif; ?>

        <div style="margin-top:12px;">
          <input type="file"
                 name="image"
                 accept="image/png,image/jpeg,image/webp"
                 class="pos-input"
                 style="padding:4px 6px;">
          <p class="pos-help-text">
            JPG / PNG / WEBP up to 2&nbsp;MB.<br>
            Stored under
            <code>/modules/POS/Assets/products/cat_{category}/org_{org}</code>.
          </p>
        </div>
      </div>
    </div>

    <!-- ACTIONS -->
    <div class="pos-actions">
      <div class="pos-actions-text">
        Tip: After saving, open <strong>Sales Register</strong> and search this product
        to confirm price &amp; image are hydrated correctly.
      </div>
      <div style="display:flex; gap:8px;">
        <a href="<?= $base ?>/products" class="pos-btn pos-btn-secondary">Cancel</a>
        <button type="submit" class="pos-btn pos-btn-primary">✓ Update Product</button>
      </div>
    </div>
  </form>
</div>