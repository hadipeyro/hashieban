# Hashieban

Hashieban is a WooCommerce profit and margin management plugin designed for Iranian online stores.

## Project status

**Pre-alpha**

The plugin is currently under active development and is not ready for production use.

## Main goal

Hashieban helps WooCommerce store owners understand the actual profitability of their sales.

The plugin is designed to help store managers:

- Calculate real order profit
- Understand product and order margins
- Track direct order costs
- Detect low-margin sales
- Identify loss-making orders
- Detect incomplete financial data
- Make better pricing and sales decisions

## Development principles

- Incremental and testable development
- Clear separation between business logic and WordPress integration
- Compatibility with modern WooCommerce order storage
- Use of official WordPress and WooCommerce APIs
- Secure handling of administrative actions and financial data
- Localization-ready implementation
- Minimal external dependencies
- No unnecessary external services
- No Docker dependency
- Financial correctness before visual complexity

## Architecture direction

Hashieban will keep financial and profit calculation logic separated from WordPress and WooCommerce integration as much as possible.

The project will gradually be divided into areas such as:

- Domain logic
- WooCommerce integration
- Persistence
- Administration
- Reporting
- Tests

The goal is to keep the core profit-calculation logic independently testable and maintainable.

## Current phase

Repository initialization and product specification.

## Development status

The project is currently being developed incrementally.

Each development task should result in a small, meaningful Git commit.