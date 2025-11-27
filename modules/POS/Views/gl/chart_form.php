<?php
/**
 * @var string      $title
 * @var string      $base
 * @var array|null  $account   // null for create, row for edit
 * @var array       $types     // ['asset'=>'Asset', ...]
 */

// convenience
$isEdit = !empty($account) && !empty($account['id']);

// old form values (if you ever pass them)
$val = function (string $key, $default = '') use ($account) {
    if (isset($_POST[$key])) {
        return (string)$_POST[$key];
    }
    if ($account && isset($account[$key])) {
        return (string)$account[$key];
    }
    return (string)$default;
};

// form action
$action = $isEdit
    ? $base . '/gl/chart/' . (int)$account['id']
    : $base . '/gl/chart/create';

// active checkbox
$isActive = $val('is_active', ($account['is_active'] ?? 1)) ? 1 : 0;
?>
<style>
    .gl-coa-form-page {
        max-width: 720px;
        margin: 0 auto;
    }
    .gl-coa-form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .gl-coa-form-title {
        font-size: 1.45rem;
        font-weight: 600;
        margin: 0;
    }
    .gl-coa-form-subtitle {
        margin: 0.25rem 0 0;
        color: #6b7280;
        font-size: 0.9rem;
    }
    .gl-coa-form-card {
        background: #ffffff;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
        padding: 1.1rem 1.2rem 1.2rem;
        box-shadow: 0 1px 2px rgba(15,23,42,0.06);
    }
    .gl-coa-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.9rem 1.2rem;
    }
    .gl-coa-form-field label {
        display: block;
        font-size: 0.8rem;
        font-weight: 500;
        color: #4b5563;
        margin-bottom: 0.25rem;
    }
    .gl-coa-form-input,
    .gl-coa-form-select {
        width: 100%;
        border-radius: 0.55rem;
        border: 1px solid #d1d5db;
        padding: 0.5rem 0.7rem;
        font-size: 0.9rem;
    }
    .gl-coa-form-input:focus,
    .gl-coa-form-select:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 1px rgba(79,70,229,0.15);
    }
    .gl-coa-form-footer {
        margin-top: 1.1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .gl-coa-form-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .gl-coa-btn {
        border-radius: 999px;
        border: none;
        padding: 0.5rem 1.1rem;
        font-size: 0.9rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        text-decoration: none;
    }
    .gl-coa-btn-primary {
        background: #4f46e5;   /* brand purple */
        color: #ffffff;
    }
    .gl-coa-btn-ghost {
        background: transparent;
        color: #4b5563;
        border: 1px solid #e5e7eb;
    }
    .gl-coa-switch {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.82rem;
        color: #4b5563;
    }
    @media (max-width: 640px) {
        .gl-coa-form-grid {
            grid-template-columns: 1fr;
        }
        .gl-coa-form-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="gl-coa-form-page">
    <div class="gl-coa-form-header">
        <div>
            <h1 class="gl-coa-form-title">
                <?= htmlspecialchars($title ?: ($isEdit ? 'Edit account' : 'Add account')) ?>
            </h1>
            <p class="gl-coa-form-subtitle">
                Define how this account will be used by POS postings.
            </p>
        </div>
        <a href="<?= htmlspecialchars($base . '/gl/chart') ?>" class="gl-coa-btn gl-coa-btn-ghost">
            ← Back to chart
        </a>
    </div>

    <form method="post" action="<?= htmlspecialchars($action) ?>">
        <div class="gl-coa-form-card">
            <div class="gl-coa-form-grid">
                <div class="gl-coa-form-field">
                    <label for="code">Code</label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        class="gl-coa-form-input"
                        required
                        value="<?= htmlspecialchars($val('code')) ?>"
                        placeholder="e.g. 1000"
                    >
                </div>

                <div class="gl-coa-form-field">
                    <label for="type">Type</label>
                    <select id="type" name="type" class="gl-coa-form-select" required>
                        <option value="">Select type…</option>
                        <?php foreach ($types as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                <?= $val('type') === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="gl-coa-form-field">
                    <label for="name">Name</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        class="gl-coa-form-input"
                        required
                        value="<?= htmlspecialchars($val('name')) ?>"
                        placeholder="e.g. Cash on hand"
                    >
                </div>

                <div class="gl-coa-form-field">
                    <label for="parent_code">Parent code (optional)</label>
                    <input
                        id="parent_code"
                        name="parent_code"
                        type="text"
                        class="gl-coa-form-input"
                        value="<?= htmlspecialchars($val('parent_code')) ?>"
                        placeholder="e.g. 1000"
                    >
                </div>
            </div>

            <div class="gl-coa-form-footer">
                <label class="gl-coa-switch">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        <?= $isActive ? 'checked' : '' ?>
                    >
                    <span>Active account</span>
                </label>

                <div class="gl-coa-form-actions">
                    <button type="submit" class="gl-coa-btn gl-coa-btn-primary">
                        <?= $isEdit ? 'Save changes' : 'Create account' ?>
                    </button>

                    <a href="<?= htmlspecialchars($base . '/gl/chart') ?>"
                       class="gl-coa-btn gl-coa-btn-ghost">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>