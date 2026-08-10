<?php

declare(strict_types=1);

namespace Hashieban\Admin;

use Hashieban\Security\Csv;
use Hashieban\Security\Json;
use Hashieban\Security\Capabilities;
use DateTimeImmutable;
use Hashieban\Finance\ExpenseCategoryRepository;
use Hashieban\Integration\WooCommerce\Analytics\ExpenseIntelligenceService;
use Hashieban\Support\Currency;
use Hashieban\Support\JalaliDate;

final class ExpenseIntelligencePage
{
    private ExpenseIntelligenceService $expenses;
    private ExpenseCategoryRepository $categories;

    public function __construct(
        ExpenseIntelligenceService $expenses,
        ExpenseCategoryRepository $categories
    ) {
        $this->expenses = $expenses;
        $this->categories = $categories;
    }

    public function register(): void
    {
        add_action(
            'admin_post_hashieban_save_expense_budgets',
            array($this, 'saveBudgets')
        );

        add_action(
            'admin_post_hashieban_export_expense_intelligence',
            array($this, 'exportCsv')
        );
    }

    public function render(): void
    {
        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            wp_die(esc_html('شما اجازه دسترسی به این بخش را ندارید.'));
        }

        list($start, $end, $range) = $this->resolveDateRange();
        $report = $this->expenses->getReport($start, $end);
        $currency = (string) $report['currency'];
        $precision = (int) $report['precision'];
        $payload = $this->buildChartPayload($report, $currency, $precision);
        $topCategory = is_array($report['top_category']) ? $report['top_category'] : null;

        ?>
        <div class="wrap hb-expense-intelligence-page">
            <section class="hb-expense-intelligence-hero">
                <div>
                    <span class="hb-expense-intelligence-hero__eyebrow">حاشیه‌بان BI · Expense Intelligence</span>
                    <h1>هوش هزینه‌ها</h1>
                    <p>
                        هزینه‌ها فقط عددی برای کم‌شدن از سود نیستند؛ این صفحه نشان می‌دهد پول کجا خرج می‌شود،
                        کدام دسته سریع‌تر رشد کرده، چه چیزی از بودجه عبور کرده و فشار هزینه روی فروش و سود چقدر است.
                    </p>
                    <div class="hb-expense-intelligence-hero__meta">
                        <span>بازه: <strong><?php echo esc_html(JalaliDate::format($start) . ' تا ' . JalaliDate::format($end)); ?></strong></span>
                        <span>بودجه‌ها: <strong>ماهانه</strong></span>
                    </div>
                </div>
                <div class="hb-expense-intelligence-hero__score">
                    <small>کل هزینه عملیاتی بازه</small>
                    <strong><?php echo esc_html(Currency::formatMinor((int) $report['operating_expenses_minor'], $currency, $precision)); ?></strong>
                    <?php $this->renderDelta($report['operating_expense_change_percentage'], 'نسبت به دوره قبل'); ?>
                </div>
            </section>

            <?php if (isset($_GET['budgets_saved'])) : ?>
                <div class="notice notice-success is-dismissible"><p>بودجه‌های ماهانه هزینه ذخیره شد.</p></div>
            <?php endif; ?>

            <?php $this->renderRangeFilters($range, $start, $end); ?>

            <section class="hb-expense-intelligence-kpis">
                <?php
                $this->renderKpi(
                    'هزینه‌های قابل دسته‌بندی',
                    Currency::formatMinor((int) $report['tracked_category_expenses_minor'], $currency, $precision),
                    null,
                    'هزینه‌های سفارش + هزینه‌های کلی که دسته دارند'
                );
                $this->renderKpi(
                    'فشار هزینه بر فروش',
                    $this->percentage($report['expense_to_revenue_percentage']),
                    null,
                    'سهم هزینه عملیاتی از فروش خالص'
                );
                $this->renderKpi(
                    'فشار هزینه بر سود ناخالص',
                    $this->percentage($report['expense_to_gross_profit_percentage']),
                    null,
                    'هزینه عملیاتی در برابر سود بعد از COGS'
                );
                $this->renderKpi(
                    'مصرف بودجه',
                    $this->percentage($report['budget_utilization_percentage']),
                    null,
                    'بودجه متناظر بازه از روی بودجه ماهانه'
                );
                $this->renderKpi(
                    'دسته‌های بالاتر از بودجه',
                    number_format_i18n((int) $report['over_budget_count']),
                    null,
                    'فقط دسته‌هایی که برایشان بودجه تعیین شده'
                );
                $this->renderKpi(
                    'بیشترین دسته هزینه',
                    $topCategory
                        ? (string) $topCategory['name']
                        : '—',
                    $topCategory['change_percentage'] ?? null,
                    $topCategory
                        ? Currency::formatMinor((int) $topCategory['amount_minor'], $currency, $precision)
                        : 'داده کافی وجود ندارد'
                );
                ?>
            </section>

            <section class="hb-expense-intelligence-chart-grid">
                <article class="hb-expense-intelligence-card">
                    <div class="hb-expense-intelligence-card__header">
                        <div><h2>ترکیب هزینه‌های قابل دسته‌بندی</h2><p>سهم هر دسته با همان رنگی که خودت تعریف کرده‌ای.</p></div>
                        <span class="hb-expense-intelligence-chip">Category Mix</span>
                    </div>
                    <div class="hb-expense-intelligence-chart"><canvas id="hashieban-expense-category-chart"></canvas></div>
                </article>

                <article class="hb-expense-intelligence-card hb-expense-intelligence-card--wide">
                    <div class="hb-expense-intelligence-card__header">
                        <div><h2>روند هزینه در برابر فروش</h2><p>تشخیص افزایش فشار هزینه همزمان با رشد یا افت فروش.</p></div>
                        <span class="hb-expense-intelligence-chip">Cost Trend</span>
                    </div>
                    <div class="hb-expense-intelligence-chart hb-expense-intelligence-chart--large"><canvas id="hashieban-expense-trend-chart"></canvas></div>
                </article>
            </section>

            <section class="hb-expense-intelligence-chart-grid">
                <article class="hb-expense-intelligence-card hb-expense-intelligence-card--wide">
                    <div class="hb-expense-intelligence-card__header">
                        <div><h2>بودجه در برابر عملکرد واقعی</h2><p>بودجه ماهانه به تناسب طول بازه برای مقایسه تبدیل می‌شود.</p></div>
                        <span class="hb-expense-intelligence-chip">Budget vs Actual</span>
                    </div>
                    <div class="hb-expense-intelligence-chart hb-expense-intelligence-chart--large"><canvas id="hashieban-expense-budget-chart"></canvas></div>
                </article>

                <article class="hb-expense-intelligence-card">
                    <div class="hb-expense-intelligence-card__header">
                        <div><h2>ساختار هزینه</h2><p>COGS در کنار هزینه‌های سفارش، ثابت و فروشگاه.</p></div>
                    </div>
                    <div class="hb-expense-intelligence-chart"><canvas id="hashieban-expense-structure-chart"></canvas></div>
                </article>
            </section>

            <section class="hb-expense-intelligence-card hb-expense-intelligence-table-card">
                <div class="hb-expense-intelligence-card__header">
                    <div><h2>تحلیل دسته‌های هزینه</h2><p>مبلغ، سهم، تغییر نسبت به دوره قبل و وضعیت بودجه.</p></div>
                    <a class="button" href="<?php echo esc_url($this->exportUrl($start, $end)); ?>">خروجی CSV</a>
                </div>
                <?php $this->renderCategoryTable((array) $report['category_rows'], $currency, $precision); ?>
            </section>

            <section class="hb-expense-intelligence-grid">
                <article class="hb-expense-intelligence-card">
                    <div class="hb-expense-intelligence-card__header"><div><h2>هزینه‌های پرتکرار فروشگاه</h2><p>عنوان‌هایی که چند بار در دفتر هزینه ثبت شده‌اند.</p></div></div>
                    <?php $this->renderRecurringTable((array) $report['recurring_expenses'], $currency, $precision); ?>
                </article>

                <article class="hb-expense-intelligence-card">
                    <div class="hb-expense-intelligence-card__header"><div><h2>پیک‌های هزینه</h2><p>بازه‌هایی که بیشترین هزینه عملیاتی را داشته‌اند.</p></div></div>
                    <?php $this->renderSpikeList((array) $report['spike_days'], $currency, $precision); ?>
                </article>
            </section>

            <section class="hb-expense-intelligence-card hb-expense-intelligence-budget-card">
                <div class="hb-expense-intelligence-card__header">
                    <div><h2>بودجه ماهانه دسته‌های هزینه</h2><p>عدد صفر یا خالی یعنی برای آن دسته بودجه‌ای تعریف نشده است.</p></div>
                </div>
                <?php $this->renderBudgetForm((array) $report['category_rows'], $currency, $precision, $range, $start, $end); ?>
            </section>

            <script id="hashieban-expense-intelligence-data" type="application/json"><?php echo Json::forHtmlScript($payload); ?></script>
        </div>
        <?php
    }

    public function saveBudgets(): void
    {
        if (! Capabilities::can(Capabilities::MANAGE_FINANCE)) {
            wp_die(esc_html('شما اجازه تغییر بودجه‌ها را ندارید.'));
        }

        check_admin_referer('hashieban_save_expense_budgets');

        $budgets = isset($_POST['budgets']) && is_array($_POST['budgets'])
            ? wp_unslash($_POST['budgets'])
            : array();

        $this->expenses->saveMonthlyBudgets($budgets);

        $range = isset($_POST['range'])
            ? sanitize_key((string) $_POST['range'])
            : '30d';

        $args = array(
            'page' => 'hashieban-expense-intelligence',
            'range' => $range,
            'budgets_saved' => '1',
        );

        if ($range === 'custom') {
            $args['start_date'] = isset($_POST['start_date'])
                ? sanitize_text_field((string) $_POST['start_date'])
                : '';
            $args['end_date'] = isset($_POST['end_date'])
                ? sanitize_text_field((string) $_POST['end_date'])
                : '';
        }

        $redirect = add_query_arg(
            $args,
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    public function exportCsv(): void
    {
        if (! Capabilities::can(Capabilities::VIEW_REPORTS)) {
            wp_die(esc_html('شما اجازه دریافت این گزارش را ندارید.'));
        }

        check_admin_referer('hashieban_export_expense_intelligence');

        $timezone = wp_timezone();
        $startValue = isset($_GET['start']) ? sanitize_text_field(wp_unslash($_GET['start'])) : '';
        $endValue = isset($_GET['end']) ? sanitize_text_field(wp_unslash($_GET['end'])) : '';
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startValue, $timezone);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endValue, $timezone);

        if (! $start || ! $end) {
            wp_die(esc_html('بازه گزارش معتبر نیست.'));
        }

        $report = $this->expenses->getReport(
            $start->setTime(0, 0, 0),
            $end->setTime(23, 59, 59)
        );
        $currency = (string) $report['currency'];
        $precision = (int) $report['precision'];

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="hashieban-expense-intelligence.csv"');
        echo "\xEF\xBB\xBF";

        $handle = fopen('php://output', 'w');

        if ($handle === false) {
            exit;
        }

        fputcsv($handle, Csv::protectRow(array(
            'دسته',
            'هزینه واقعی (' . Currency::label($currency) . ')',
            'سهم از هزینه',
            'تغییر نسبت به دوره قبل',
            'بودجه ماهانه (' . Currency::label($currency) . ')',
            'بودجه متناظر بازه (' . Currency::label($currency) . ')',
            'مصرف بودجه',
        )));

        foreach ((array) $report['category_rows'] as $row) {
            fputcsv($handle, Csv::protectRow(array(
                (string) $row['name'],
                Currency::minorToDisplayInput((int) $row['amount_minor'], $currency, $precision),
                $this->csvPercentage($row['share_percentage']),
                $this->csvPercentage($row['change_percentage']),
                Currency::minorToDisplayInput((int) $row['monthly_budget_minor'], $currency, $precision),
                Currency::minorToDisplayInput((int) $row['period_budget_minor'], $currency, $precision),
                $this->csvPercentage($row['budget_utilization_percentage']),
            )));
        }

        fclose($handle);
        exit;
    }

    private function renderCategoryTable(array $rows, string $currency, int $precision): void
    {
        if ($rows === array()) {
            echo '<div class="hb-expense-intelligence-empty">هنوز هزینه دسته‌بندی‌شده‌ای در این بازه وجود ندارد.</div>';
            return;
        }
        ?>
        <div class="hb-expense-intelligence-table-wrap">
            <table class="widefat striped">
                <thead><tr><th>دسته</th><th>هزینه</th><th>سهم</th><th>تغییر</th><th>بودجه بازه</th><th>مصرف بودجه</th><th>وضعیت</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><span class="hb-expense-intelligence-dot" style="--hb-category-color: <?php echo esc_attr((string) $row['color']); ?>"></span><?php echo esc_html((string) $row['name']); ?></td>
                        <td><strong><?php echo esc_html(Currency::formatMinor((int) $row['amount_minor'], $currency, $precision)); ?></strong></td>
                        <td><?php echo esc_html($this->percentage($row['share_percentage'])); ?></td>
                        <td><?php $this->renderDelta($row['change_percentage'], ''); ?></td>
                        <td><?php echo (int) $row['period_budget_minor'] > 0 ? esc_html(Currency::formatMinor((int) $row['period_budget_minor'], $currency, $precision)) : '—'; ?></td>
                        <td><?php echo esc_html($this->percentage($row['budget_utilization_percentage'])); ?></td>
                        <td>
                            <?php if ((int) $row['period_budget_minor'] <= 0) : ?>
                                <span class="hb-expense-intelligence-status is-neutral">بدون بودجه</span>
                            <?php elseif (! empty($row['over_budget'])) : ?>
                                <span class="hb-expense-intelligence-status is-danger">بالاتر از بودجه</span>
                            <?php else : ?>
                                <span class="hb-expense-intelligence-status is-good">در محدوده</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function renderRecurringTable(array $rows, string $currency, int $precision): void
    {
        if ($rows === array()) {
            echo '<div class="hb-expense-intelligence-empty">هزینه پرتکراری در این بازه ثبت نشده است.</div>';
            return;
        }
        ?>
        <div class="hb-expense-intelligence-table-wrap">
            <table class="widefat striped">
                <thead><tr><th>عنوان</th><th>دفعات</th><th>مجموع</th><th>میانگین</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo esc_html((string) ($row['title'] ?? '—')); ?></td>
                        <td><?php echo esc_html(number_format_i18n((int) ($row['occurrences'] ?? 0))); ?></td>
                        <td><?php echo esc_html(Currency::formatMinor((int) ($row['amount_minor'] ?? 0), $currency, $precision)); ?></td>
                        <td><?php echo esc_html(Currency::formatMinor((int) ($row['average_minor'] ?? 0), $currency, $precision)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function renderSpikeList(array $rows, string $currency, int $precision): void
    {
        if ($rows === array()) {
            echo '<div class="hb-expense-intelligence-empty">داده کافی برای تشخیص پیک هزینه وجود ندارد.</div>';
            return;
        }

        echo '<ol class="hb-expense-intelligence-spikes">';
        foreach ($rows as $row) {
            if ((int) ($row['operating_expenses_minor'] ?? 0) <= 0) {
                continue;
            }

            echo '<li><div><strong>' . esc_html((string) ($row['label'] ?? '—')) . '</strong><small>هزینه عملیاتی</small></div><span>'
                . esc_html(Currency::formatMinor((int) $row['operating_expenses_minor'], $currency, $precision))
                . '</span></li>';
        }
        echo '</ol>';
    }

    private function renderBudgetForm(
        array $rows,
        string $currency,
        int $precision,
        string $range,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): void {
        if (! Capabilities::can(Capabilities::MANAGE_FINANCE)) {
            echo '<div class="hb-expense-intelligence-empty">دسترسی شما فقط خواندنی است؛ تغییر بودجه نیازمند دسترسی مدیریت مالی حاشیه‌بان است.</div>';
            return;
        }

        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="hb-expense-intelligence-budget-form">
            <input type="hidden" name="action" value="hashieban_save_expense_budgets">
            <input type="hidden" name="range" value="<?php echo esc_attr($range); ?>">
            <?php if ($range === 'custom') : ?>
                <input type="hidden" name="start_date" value="<?php echo esc_attr(JalaliDate::numeric($start)); ?>">
                <input type="hidden" name="end_date" value="<?php echo esc_attr(JalaliDate::numeric($end)); ?>">
            <?php endif; ?>
            <?php wp_nonce_field('hashieban_save_expense_budgets'); ?>
            <div class="hb-expense-intelligence-budget-grid">
                <?php foreach ($this->budgetCategories() as $category) : ?>
                    <?php
                    $id = (string) $category['id'];
                    $monthlyMinor = $this->expenses->monthlyBudgetMinor($id, $currency, $precision);
                    $value = $monthlyMinor > 0
                        ? Currency::minorToDisplayInput($monthlyMinor, $currency, $precision)
                        : '';
                    ?>
                    <label>
                        <span><i style="--hb-category-color: <?php echo esc_attr((string) $category['color']); ?>"></i><?php echo esc_html((string) $category['name']); ?></span>
                        <input type="text" inputmode="decimal" name="budgets[<?php echo esc_attr($id); ?>]" value="<?php echo esc_attr($value); ?>" placeholder="0">
                        <small><?php echo esc_html(Currency::label($currency)); ?> در ماه</small>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="button button-primary">ذخیره بودجه‌های ماهانه</button>
        </form>
        <?php
    }

    private function budgetCategories(): array
    {
        return $this->categories->all();
    }

    private function renderRangeFilters(string $range, DateTimeImmutable $start, DateTimeImmutable $end): void
    {
        $ranges = array(
            '7d' => '۷ روز',
            '30d' => '۳۰ روز',
            '90d' => '۹۰ روز',
            '6m' => '۶ ماه',
            'year' => '۱ سال',
            '2y' => '۲ سال',
            'all' => 'همه زمان‌ها',
        );
        ?>
        <div class="hb-expense-intelligence-range">
            <div class="hb-expense-intelligence-range__buttons">
                <?php foreach ($ranges as $key => $label) : ?>
                    <a class="<?php echo $range === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(array('page' => 'hashieban-expense-intelligence', 'range' => $key), admin_url('admin.php'))); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>
            <form method="get" class="hb-expense-intelligence-range__custom">
                <input type="hidden" name="page" value="hashieban-expense-intelligence">
                <input type="hidden" name="range" value="custom">
                <label>از <input type="text" name="start_date" value="<?php echo esc_attr(JalaliDate::numeric($start)); ?>" autocomplete="off" data-jdp></label>
                <label>تا <input type="text" name="end_date" value="<?php echo esc_attr(JalaliDate::numeric($end)); ?>" autocomplete="off" data-jdp></label>
                <button type="submit" class="button button-primary">اعمال</button>
            </form>
        </div>
        <?php
    }

    private function resolveDateRange(): array
    {
        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);
        $end = $now->setTime(23, 59, 59);
        $range = isset($_GET['range']) ? sanitize_key(wp_unslash($_GET['range'])) : '30d';

        switch ($range) {
            case '7d':
                $start = $now->modify('-6 days')->setTime(0, 0, 0);
                break;
            case '90d':
                $start = $now->modify('-89 days')->setTime(0, 0, 0);
                break;
            case '6m':
                $start = $now->modify('-6 months')->setTime(0, 0, 0);
                break;
            case 'year':
                $start = $now->modify('-1 year')->setTime(0, 0, 0);
                break;
            case '2y':
                $start = $now->modify('-2 years')->setTime(0, 0, 0);
                break;
            case 'all':
                $start = $this->resolveAllTimeStart($now);
                break;
            case 'custom':
                $custom = $this->resolveCustomRange();
                if ($custom !== null) {
                    return array($custom[0], $custom[1], 'custom');
                }
                $range = '30d';
                $start = $now->modify('-29 days')->setTime(0, 0, 0);
                break;
            case '30d':
            default:
                $range = '30d';
                $start = $now->modify('-29 days')->setTime(0, 0, 0);
                break;
        }

        return array($start, $end, $range);
    }

    private function resolveCustomRange(): ?array
    {
        $startValue = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : '';
        $endValue = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : '';

        if ($startValue === '' || $endValue === '') {
            return null;
        }

        $gregorianStart = JalaliDate::parseInputToGregorianYmd($startValue);
        $gregorianEnd = JalaliDate::parseInputToGregorianYmd($endValue);

        if ($gregorianStart === null || $gregorianEnd === null) {
            return null;
        }

        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $gregorianStart, wp_timezone());
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $gregorianEnd, wp_timezone());

        if (! $start || ! $end) {
            return null;
        }

        $start = $start->setTime(0, 0, 0);
        $end = $end->setTime(23, 59, 59);

        if ($start > $end) {
            $temporary = $start;
            $start = $end->setTime(0, 0, 0);
            $end = $temporary->setTime(23, 59, 59);
        }

        return array($start, $end);
    }

    private function resolveAllTimeStart(DateTimeImmutable $fallback): DateTimeImmutable
    {
        $orders = wc_get_orders(array(
            'status' => array('processing', 'completed', 'refunded'),
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'ASC',
            'return' => 'objects',
        ));

        if (is_array($orders) && isset($orders[0])) {
            $created = $orders[0]->get_date_created();
            if ($created) {
                return (new DateTimeImmutable('@' . $created->getTimestamp()))
                    ->setTimezone(wp_timezone())
                    ->setTime(0, 0, 0);
            }
        }

        return $fallback->modify('-30 days')->setTime(0, 0, 0);
    }

    private function buildChartPayload(array $report, string $currency, int $precision): array
    {
        $categoryLabels = array();
        $categoryValues = array();
        $categoryColors = array();
        $budgetLabels = array();
        $budgetActual = array();
        $budgetTarget = array();

        foreach ((array) $report['category_rows'] as $row) {
            if ((int) $row['amount_minor'] > 0) {
                $categoryLabels[] = (string) $row['name'];
                $categoryValues[] = Currency::minorToDisplayNumber((int) $row['amount_minor'], $currency, $precision);
                $categoryColors[] = (string) $row['color'];
            }

            if ((int) $row['period_budget_minor'] > 0) {
                $budgetLabels[] = (string) $row['name'];
                $budgetActual[] = Currency::minorToDisplayNumber((int) $row['amount_minor'], $currency, $precision);
                $budgetTarget[] = Currency::minorToDisplayNumber((int) $row['period_budget_minor'], $currency, $precision);
            }
        }

        $trendLabels = array();
        $trendOperating = array();
        $trendRevenue = array();

        foreach ((array) $report['trend'] as $row) {
            $trendLabels[] = (string) $row['label'];
            $trendOperating[] = Currency::minorToDisplayNumber((int) $row['operating_expenses_minor'], $currency, $precision);
            $trendRevenue[] = Currency::minorToDisplayNumber((int) $row['revenue_minor'], $currency, $precision);
        }

        return array(
            'currencyLabel' => Currency::label($currency),
            'categories' => array(
                'labels' => $categoryLabels,
                'values' => $categoryValues,
                'colors' => $categoryColors,
            ),
            'trend' => array(
                'labels' => $trendLabels,
                'expenses' => $trendOperating,
                'revenue' => $trendRevenue,
            ),
            'budget' => array(
                'labels' => $budgetLabels,
                'actual' => $budgetActual,
                'target' => $budgetTarget,
            ),
            'structure' => array(
                'labels' => array('قیمت خرید کالاها', 'هزینه سفارش‌ها', 'هزینه ثابت سفارش', 'هزینه‌های کلی فروشگاه'),
                'values' => array(
                    Currency::minorToDisplayNumber((int) $report['cogs_minor'], $currency, $precision),
                    Currency::minorToDisplayNumber((int) $report['direct_costs_minor'], $currency, $precision),
                    Currency::minorToDisplayNumber((int) $report['global_order_costs_minor'], $currency, $precision),
                    Currency::minorToDisplayNumber((int) $report['store_expenses_minor'], $currency, $precision),
                ),
            ),
        );
    }

    private function renderKpi(string $label, string $value, $change, string $hint): void
    {
        ?>
        <article class="hb-expense-intelligence-kpi">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($value); ?></strong>
            <?php if ($change !== null) : ?><?php $this->renderDelta($change, ''); ?><?php endif; ?>
            <small><?php echo esc_html($hint); ?></small>
        </article>
        <?php
    }

    private function renderDelta($value, string $label): void
    {
        if ($value === null) {
            echo '<span class="hb-expense-intelligence-delta is-neutral">داده مقایسه کافی نیست</span>';
            return;
        }

        $number = (float) $value;
        $class = $number > 0 ? 'is-up' : ($number < 0 ? 'is-down' : 'is-neutral');
        $prefix = $number > 0 ? '+' : '';
        $text = $prefix . number_format_i18n($number, 1) . '٪';

        if ($label !== '') {
            $text .= ' ' . $label;
        }

        echo '<span class="hb-expense-intelligence-delta ' . esc_attr($class) . '">' . esc_html($text) . '</span>';
    }

    private function percentage($value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format_i18n((float) $value, 1) . '٪';
    }

    private function csvPercentage($value): string
    {
        if ($value === null) {
            return '';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function exportUrl(DateTimeImmutable $start, DateTimeImmutable $end): string
    {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'hashieban_export_expense_intelligence',
                    'start' => $start->format('Y-m-d'),
                    'end' => $end->format('Y-m-d'),
                ),
                admin_url('admin-post.php')
            ),
            'hashieban_export_expense_intelligence'
        );
    }
}
