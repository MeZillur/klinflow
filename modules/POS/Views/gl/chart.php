<?php
/** @var string $title */
/** @var string $base */
/** @var array  $rows */
/** @var string $q */
/** @var string $type */
/** @var array  $types */
/** @var int|null $totalAccounts */
?>
<style>
    .gl-coa-page {
        max-width: 1120px;
        margin: 0 auto;
    }
    .gl-coa-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .gl-coa-title {
        font-size: 1.6rem;
        font-weight: 600;
        margin: 0;
    }
    .gl-coa-subtitle {
        margin: 0.25rem 0 0;
        color: #6b7280;
        font-size: 0.9rem;
    }
    .gl-coa-header-right {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .gl-coa-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem 0.7rem;
        border-radius: 999px;
        background: #ecfdf3;          /* soft KlinFlow green */
        color: #16a34a;
        font-size: 0.78rem;
        font-weight: 500;
        white-space: nowrap;
    }

    .gl-coa-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
        margin-bottom: 1rem;
    }
    .gl-coa-input,
    .gl-coa-select {
        border-radius: 0.55rem;
        border: 1px solid #d1d5db;
        padding: 0.45rem 0.7rem;
        font-size: 0.9rem;
        min-width: 220px;
    }
    .gl-coa-input:focus,
    .gl-coa-select:focus {
        outline: none;
        border-color: #4f46e5; /* KlinFlow purple */
        box-shadow: 0 0 0 1px rgba(79,70,229,0.12);
    }
    .gl-coa-btn {
        border-radius: 999px;
        border: none;
        padding: 0.45rem 0.95rem;
        font-size: 0.9rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
    .gl-coa-btn-primary {
        background: #4f46e5; /* primary purple */
        color: #fff;
    }
    .gl-coa-btn-ghost {
        background: transparent;
        color: #6b7280;
    }

    .gl-coa-card {
        background: #ffffff;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
        padding: 0.9rem 1rem;
        box-shadow: 0 1px 2px rgba(15,23,42,0.04);
    }
    .gl-coa-empty {
        text-align: left;
        font-size: 0.9rem;
        color: #6b7280;
    }

    .gl-coa-table-wrapper {
        margin-top: 0.5rem;
        overflow-x: auto;
    }
    .gl-coa-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.88rem;
    }
    .gl-coa-table th {
        text-align: left;
        padding: 0.55rem 0.5rem;
        border-bottom: 1px solid #e5e7eb;
        font-weight: 500;
        color: #6b7280;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .gl-coa-table td {
        padding: 0.5rem 0.5rem;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }
    .gl-coa-code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 0.82rem;
        color: #111827;
    }
    .gl-coa-type-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.15rem 0.55rem;
        border-radius: 999px;
        background: #eef2ff;   /* soft purple */
        color: #4f46e5;
        font-size: 0.75rem;
        font-weight: 500;
    }
    .gl-coa-active {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.8rem;
    }
    .gl-coa-active-dot {
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: #16a34a; /* brand green */
    }
    .gl-coa-active-dot.inactive {
        background: #9ca3af;
    }
    .gl-coa-actions a {
        font-size: 0.8rem;
        color: #4f46e5;
        text-decoration: none;
    }
    .gl-coa-actions a:hover {
        text-decoration: underline;
    }

    @media (max-width: 640px) {
        .gl-coa-header {
            flex-direction: column;
        }
        .gl-coa-input,
        .gl-coa-select {
            min-width: 100%;
        }
        .gl-coa-header-right {
            width: 100%;
            justify-content: flex-start;
        }
    }
</style>

<div class="gl-coa-page">

    <div class="gl-coa-header">
        <div>
            <h1 class="gl-coa-title"><?= htmlspecialchars($title) ?></h1>
            <p class="gl-coa-subtitle">
                Company-wide chart of accounts for POS postings.
            </p>
        </div>

        <div class="gl-coa-header-right">
            <a href="<?= htmlspecialchars($base.'/gl/chart/create') ?>"
               class="gl-coa-btn gl-coa-btn-primary">
                + Add account
            </a>
            <span class="gl-coa-badge">
                <?= (int)($totalAccounts ?? count($rows)) ?> accounts
            </span>
        </div>
    </div>

    <form method="get" class="gl-coa-filters">
        <input
            type="text"
            name="q"
            class="gl-coa-input"
            value="<?= htmlspecialchars($q) ?>"
            placeholder="Search by code or name…"
        />

        <select name="type" class="gl-coa-select">
            <option value="">All types</option>
            <?php foreach ($types as $key => $label): ?>
                <option value="<?= htmlspecialchars($key) ?>" <?= $key === $type ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="gl-coa-btn gl-coa-btn-primary">
            Filter
        </button>

        <a href="<?= htmlspecialchars($base.'/gl/chart') ?>" class="gl-coa-btn gl-coa-btn-ghost">
            Reset
        </a>
    </form>

    <div class="gl-coa-card">
        <?php if (!$rows): ?>
            <div class="gl-coa-empty">
                <strong>No accounts yet.</strong><br>
                Add some rows into <code>pos_accounts</code> (code, name, type) and they’ll show up here.
            </div>
        <?php else: ?>
            <div class="gl-coa-table-wrapper">
                <table class="gl-coa-table">
                    <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Parent</th>
                        <th>Active</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="gl-coa-code">
                                <?= htmlspecialchars($row['code']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($row['name']) ?>
                            </td>
                            <td>
                                <span class="gl-coa-type-pill">
                                    <?= htmlspecialchars($types[$row['type']] ?? $row['type']) ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($row['parent_code'] ?? '') ?>
                            </td>
                            <td>
                                <span class="gl-coa-active">
                                    <span class="gl-coa-active-dot <?= empty($row['is_active']) ? 'inactive' : '' ?>"></span>
                                    <?= !empty($row['is_active']) ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="gl-coa-actions">
                                <a href="<?= htmlspecialchars($base.'/gl/chart/'.$row['id'].'/edit') ?>">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>