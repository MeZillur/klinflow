<?php
/** @var string $title */
/** @var string $base */
?>
<div class="container mt-4 mb-5">
    <div class="row">
        <div class="col-12 mb-3">
            <h1 class="h3"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="text-muted mb-4">
                All core POS accounting reports will live here: Trial Balance, Ledger,
                Profit &amp; Loss and Balance Sheet.
            </p>
        </div>

        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h5 mb-2">Coming soon 🚧</h2>
                    <p class="mb-3">
                        We are still wiring POS GL postings from payments, expenses and sales.
                        As soon as that’s done, you’ll see:
                    </p>
                    <ul class="mb-3">
                        <li>Trial Balance</li>
                        <li>Account Ledger</li>
                        <li>Profit &amp; Loss</li>
                        <li>Balance Sheet</li>
                    </ul>
                    <p class="mb-0 text-muted">
                        For now you can review postings in
                        <a href="<?php echo htmlspecialchars($base . '/gl/journals', ENT_QUOTES, 'UTF-8'); ?>">
                            GL Journals
                        </a>.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h3 class="h6 text-uppercase text-muted mb-2">Quick links</h3>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-1">
                            <a href="<?php echo htmlspecialchars($base . '/gl/journals', ENT_QUOTES, 'UTF-8'); ?>">
                                GL Journals
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="<?php echo htmlspecialchars($base . '/gl/ledger', ENT_QUOTES, 'UTF-8'); ?>">
                                Ledger
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="<?php echo htmlspecialchars($base . '/gl/trial-balance', ENT_QUOTES, 'UTF-8'); ?>">
                                Trial Balance
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>