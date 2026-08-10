<?php

declare(strict_types=1);

namespace Hashieban\Finance;

final class ExpenseCategoryRepository
{
    private const OPTION_KEY =
        'hashieban_expense_categories';

    public function ensureDefaults(): void
    {
        $stored = get_option(
            self::OPTION_KEY,
            false
        );

        if ($stored !== false) {
            return;
        }

        $this->saveAll(
            array(
                array(
                    'id' => 'hb_shipping',
                    'name' => 'ارسال',
                    'color' => '#f59e0b',
                    'active' => true,
                ),
                array(
                    'id' => 'hb_packaging',
                    'name' => 'بسته‌بندی',
                    'color' => '#8b5cf6',
                    'active' => true,
                ),
                array(
                    'id' => 'hb_gateway',
                    'name' => 'کارمزد درگاه',
                    'color' => '#06b6d4',
                    'active' => true,
                ),
                array(
                    'id' => 'hb_tax',
                    'name' => 'مالیات',
                    'color' => '#eab308',
                    'active' => true,
                ),
                array(
                    'id' => 'hb_advertising',
                    'name' => 'تبلیغات',
                    'color' => '#ec4899',
                    'active' => true,
                ),
                array(
                    'id' => 'hb_other',
                    'name' => 'سایر',
                    'color' => '#64748b',
                    'active' => true,
                ),
            )
        );
    }

    public function all(): array
    {
        $categories = get_option(
            self::OPTION_KEY,
            array()
        );

        return is_array($categories)
        ? $categories
             : array();
    }

    public function active(): array
    {
        return array_values(
            array_filter(
                $this->all(),
                static function ($category): bool {
                    return is_array($category)
                        && ! empty($category['active']);
                }
            )
        );
    }

    public function inactive(): array
    {
        return array_values(
            array_filter(
                $this->all(),
                static function ($category): bool {
                    return is_array($category)
                        && empty($category['active']);
                }
            )
        );
    }

    public function find(
        string $id
    ): ?array {
        foreach ($this->all() as $category) {
            if (
                is_array($category)
                && isset($category['id'])
                && $category['id'] === $id
            ) {
                return $category;
            }
        }

        return null;
    }

    public function save(
        string $name,
        string $color,
        string $id = ''
    ): string {
        $name = sanitize_text_field($name);

        $safeColor = sanitize_hex_color(
            $color
        );

        if ($safeColor === null) {
            $safeColor = '#64748b';
        }

        if ($id === '') {
            $id = 'hb_'
                . str_replace(
                    '-',
                    '',
                    wp_generate_uuid4()
                );
        }

        $id = sanitize_key($id);

        $categories = $this->all();

        $found = false;

        foreach (
            $categories
            as $index => $category
        ) {
            if (
                ! is_array($category)
                || ($category['id'] ?? '')
                !== $id
            ) {
                continue;
            }

            $isActive = array_key_exists(
                'active',
                $category
            )
            ? (bool) $category['active']
					  : true;

            $categories[$index] = array(
                'id' => $id,
                'name' => $name,
                'color' => $safeColor,
                'active' => $isActive,
            );

            $found = true;
            break;
        }

        if (! $found) {
            $categories[] = array(
                'id' => $id,
                'name' => $name,
                'color' => $safeColor,
                'active' => true,
            );
        }

        $this->saveAll($categories);

        return $id;
    }

    public function activate(
        string $id
    ): void {
        $this->setActive(
            $id,
            true
        );
    }

    public function deactivate(
        string $id
    ): void {
        $this->setActive(
            $id,
            false
        );
    }

    public function fallbackId(): string
    {
        if ($this->find('hb_other')) {
            return 'hb_other';
        }

        return $this->save(
            'سایر',
            '#64748b',
            'hb_other'
        );
    }

    public function name(
        string $id
    ): string {
        $category = $this->find($id);

        if (
            $category
            && ! empty($category['name'])
        ) {
            return (string) $category['name'];
        }

        return 'سایر';
    }

    public function color(
        string $id
    ): string {
        $category = $this->find($id);

        if (
            $category
            && ! empty($category['color'])
        ) {
            return (string) $category['color'];
        }

        return '#64748b';
    }

    private function setActive(
        string $id,
        bool $active
    ): void {
        $id = sanitize_key($id);

        if ($id === '') {
            return;
        }

        $categories = $this->all();

        foreach (
            $categories
            as $index => $category
        ) {
            if (
                ! is_array($category)
                || ($category['id'] ?? '')
                !== $id
            ) {
                continue;
            }

            $categories[$index]['active'] =
                $active;

            $this->saveAll($categories);

            return;
        }
    }

    private function saveAll(
        array $categories
    ): void {
        update_option(
            self::OPTION_KEY,
            array_values($categories),
            false
        );
    }
}
