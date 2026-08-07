# Hashieban Product Specification

## 1. Product overview

Hashieban is a WooCommerce profit and margin management plugin designed primarily for Iranian online stores.

The plugin helps store owners understand how much profit each order actually generates after considering direct costs such as product cost, shipping, packaging, payment fees, refunds, and other order-related expenses.

Hashieban is not an accounting system.

Its purpose is to provide operational profit intelligence inside WooCommerce.

---

## 2. Problem

WooCommerce provides sales and revenue information, but revenue alone does not tell a store owner whether an order was profitable.

For example, an order may generate significant revenue while still producing very little profit because of:

- Product cost
- Shipping cost
- Packaging cost
- Payment gateway fees
- Discounts
- Refunds
- Additional order expenses

Store owners need a simple way to answer:

> How much profit did this order actually generate?

Hashieban is designed to answer that question.

---

## 3. Main product goal

The main goal of Hashieban is to provide reliable order-level profitability information.

The plugin should help store owners:

- Calculate order profit
- Calculate profit margin
- Understand the cost breakdown of each order
- Detect low-margin orders
- Detect loss-making orders
- Detect missing financial data
- Analyze profit over a selected period
- Make better pricing and sales decisions

---

## 4. Target users

The initial target users are small and medium-sized WooCommerce stores.

The plugin is especially useful for stores that:

- Sell physical products
- Track product purchase cost
- Have shipping expenses
- Have packaging expenses
- Pay transaction or gateway fees
- Frequently use discounts
- Process refunds
- Need simple profitability reporting

The first release will primarily target single-currency WooCommerce stores.

---

## 5. Definition of profit

Hashieban does not calculate the full accounting net profit of a business.

The internal concept used by the plugin is:

`Order Contribution Profit`

This means the profit directly attributable to a WooCommerce order.

General business expenses are outside the initial scope.

Examples of excluded expenses:

- Salaries
- Office rent
- Internet costs
- General advertising expenses
- Business taxes
- Equipment depreciation
- Administrative overhead

The user interface may use the simpler term:

`Order Profit`

but documentation must make the meaning clear.

---

## 6. Revenue

Order revenue represents the money attributable to the order.

The initial calculation includes:

- Product revenue after discounts
- Shipping amount charged to the customer
- Positive customer fees

Refunded amounts must reduce order revenue.

Taxes are not considered profit revenue.

Conceptually:

`Revenue = Product Revenue + Shipping Revenue + Positive Fees - Refunds`

---

## 7. Cost of Goods Sold

Hashieban must use the native WooCommerce Cost of Goods Sold functionality whenever available.

Hashieban must not create a second independent product cost system unless a future compatibility requirement makes it necessary.

The cost stored on the historical order item should have priority over the current product cost.

Example:

Product cost when the order was created:

`500,000`

Current product cost:

`650,000`

The historical order must continue using:

`500,000`

Changing the current product cost must not silently rewrite historical profit calculations.

---

## 8. Shipping revenue and shipping cost

Shipping revenue and actual shipping cost are two different concepts.

### Shipping revenue

The amount charged to the customer for shipping.

### Actual shipping cost

The amount actually paid by the store to deliver the order.

Example:

Shipping charged to customer:

`70,000`

Actual shipping cost:

`95,000`

Net shipping effect:

`-25,000`

Hashieban must keep these values separate.

The MVP will allow an actual shipping cost to be stored for an order.

---

## 9. Packaging cost

An order may have direct packaging expenses such as:

- Box
- Envelope
- Protective material
- Tape
- Labels
- Gift packaging

Hashieban must support a packaging cost.

The system should eventually support:

- A default store-level packaging cost
- An order-level override

The order-level value is the final source used in profit calculations.

---

## 10. Payment cost

Payment methods may generate direct transaction costs.

Hashieban should support payment cost rules.

An initial rule may contain:

- Percentage fee
- Fixed fee

Example:

`0.5% + 2,000`

The calculated amount becomes part of the order cost breakdown.

The system should also support a manually overridden payment cost when necessary.

---

## 11. Additional costs

Some direct order expenses may not fit the standard categories.

Hashieban must support additional order costs.

The MVP may initially support a simple additional cost value with a description.

Example:

`Special packaging: 35,000`

A future version may support multiple structured additional cost entries.

---

## 12. Refunds

Refund handling is part of the core profit calculation.

It is not an optional feature.

A refund may affect:

- Revenue
- Product COGS
- Profit
- Margin

Hashieban should use WooCommerce refund data and native COGS behavior whenever possible.

Shipping, packaging, and other expenses should not automatically disappear when a refund is created.

Those costs remain unless the store explicitly changes them.

---

## 13. Profit formula

The conceptual formula is:

`Profit = Revenue - Direct Costs`

Direct costs include:

- COGS
- Actual shipping cost
- Packaging cost
- Payment cost
- Additional costs

Therefore:

`Order Profit = Revenue - COGS - Shipping Cost - Packaging Cost - Payment Cost - Additional Costs`

---

## 14. Margin

Profit margin is calculated as:

`Margin % = (Order Profit / Revenue) × 100`

If revenue is zero or negative, Hashieban must not display a misleading normal percentage.

Such cases should be handled explicitly.

---

## 15. Financial data completeness

Hashieban must never present an incomplete calculation as unquestionably correct.

Each profit calculation must have a completeness state.

Initial states:

- Complete
- Incomplete

Example:

`Profit: 2,450,000`

`Status: Complete`

or:

`Profit: unavailable`

`Status: Incomplete`

Possible reasons for incomplete data include:

- Missing product COGS
- COGS functionality unavailable
- Missing required direct costs
- Invalid order data
- Calculation failure

Hashieban may show available financial information for an incomplete order, but it must clearly indicate that the final profit result is incomplete.

---

## 16. Profit snapshot

Hashieban should persist calculated order profitability.

A profit snapshot should contain at least:

- Revenue
- COGS
- Shipping cost
- Packaging cost
- Payment cost
- Additional costs
- Profit
- Margin
- Completeness status
- Missing data information
- Calculation version
- Calculation timestamp

Snapshots reduce repeated calculation overhead and make reporting faster.

A snapshot is not permanently immutable.

It may be recalculated when relevant order financial information changes.

---

## 17. Recalculation

Profit may need recalculation when:

- Order data changes
- A refund is created or modified
- Actual shipping cost changes
- Packaging cost changes
- Payment cost changes
- Additional cost changes
- The administrator requests recalculation

A change to the current product cost must not automatically rewrite historical order costs.

---

## 18. Order status handling

Profit reports should not treat failed or unpaid orders as completed sales.

The default reporting logic should focus on paid orders.

Refunds related to paid orders must affect profitability reporting.

The implementation should avoid assumptions that make custom WooCommerce order statuses impossible to support later.

---

## 19. Multi-currency

Each WooCommerce order currency must be preserved.

Hashieban must never blindly add profitability values from different currencies.

The MVP is primarily designed for single-currency stores.

If multiple currencies exist, reporting should either:

- Separate results by currency

or

- Require a future explicit conversion mechanism

No hidden currency conversion is allowed.

---

## 20. Margin Guard

Margin Guard is one of the main differentiating features of Hashieban.

The store administrator should be able to configure a minimum acceptable margin.

Example:

`Minimum Margin = 15%`

Orders can then be classified as:

- Healthy
- Low Margin
- Loss

Example:

`28% → Healthy`

`9% → Low Margin`

`Negative profit → Loss`

The MVP will initially provide warnings and reporting.

Automatic checkout or coupon blocking is outside the first release.

---

## 21. Order admin screen

Hashieban should display an understandable profit breakdown on the WooCommerce order administration screen.

Conceptual example:

Revenue                  1,000,000
COGS                      -610,000
Shipping Cost              -65,000
Packaging                  -18,000
Payment Cost                -9,500
Additional Costs           -10,000
----------------------------------
Order Profit               287,500
Margin                      28.75%

The same area should clearly display financial data completeness.

---

## 22. Profit reporting

The MVP should contain a profitability report.

The report should initially provide:

- Revenue
- COGS
- Direct costs
- Profit
- Average margin
- Number of profitable orders
- Number of low-margin orders
- Number of loss-making orders
- Number of incomplete orders

The administrator must be able to select a date range.

---

## 23. Loss-making orders

An order is considered loss-making when:

`Profit < 0`

Loss-making orders must be identifiable in reports.

The store administrator should be able to answer:

> Which sales are costing the business money instead of generating profit?

---

## 24. Missing COGS detection

Hashieban should detect products or order items that do not have sufficient COGS information.

The administrator should be able to understand whether profitability reports are reliable before making business decisions based on them.

Data quality is part of the product.

---

## 25. CSV export

The MVP should support exporting profitability data as CSV.

The initial export should contain the important order-level profitability fields.

Advanced Excel and PDF reporting are outside the MVP.

---

## 26. HPOS compatibility

Hashieban must support WooCommerce High-Performance Order Storage.

Order data must be accessed using official WooCommerce CRUD APIs.

The plugin must not rely directly on:

- `wp_posts`
- `wp_postmeta`
- Internal HPOS database tables

Hashieban must declare HPOS compatibility once the implementation has been verified.

---

## 27. Architecture direction

Financial domain logic should remain separated from WordPress integration.

Conceptually:

WooCommerce
    |
    v
Integration Layer
    |
    v
Application Layer
    |
    v
Profit Domain
    |
    +-- ProfitCalculator
    +-- ProfitResult
    +-- ProfitBreakdown
    +-- Completeness
    |
    v
Persistence
    |
    +-- Admin UI
    +-- Reports

The architecture should prioritize:

- Testability
- Maintainability
- Clear responsibilities
- Low coupling
- Financial correctness

---

## 28. Technical principles

The project should follow these principles:

- Use official WordPress APIs
- Use official WooCommerce APIs
- Support HPOS
- Use namespaces
- Keep classes small and focused
- Keep business logic independently testable
- Validate administrative capabilities
- Use nonces for state-changing administrative actions
- Sanitize input
- Escape output
- Support localization
- Avoid unnecessary external dependencies

---

## 29. Money precision

Financial calculations require predictable precision.

The domain layer must not casually rely on floating-point behavior.

WooCommerce currency precision must be considered.

The final Money representation will be decided when the profit domain model is implemented.

---

## 30. MVP scope

The first marketable version should include:

- Native WooCommerce COGS integration
- Order revenue calculation
- Refund handling
- Actual shipping cost
- Packaging cost
- Payment cost
- Additional cost
- Profit calculation
- Margin calculation
- Completeness detection
- Profit snapshots
- Order profit breakdown
- Profit reports
- Low-margin detection
- Loss-making order detection
- Missing COGS detection
- CSV export
- HPOS compatibility
- Persian-friendly administration interface
- Localization-ready implementation

---

## 31. Explicitly outside MVP

The following features are intentionally excluded from the first release:

- Full accounting system
- Payroll
- Tax accounting
- Full inventory accounting
- FIFO or LIFO accounting
- Accounting software integrations
- Automatic advertising cost attribution
- Artificial intelligence features
- Sales forecasting
- Mobile application
- Automatic checkout blocking
- Automatic coupon blocking
- Torob integration
- Emalls integration
- Multi-vendor accounting
- PDF accounting reports

These features may only be considered after the MVP is tested with real stores.

---

## 32. MVP success criteria

The MVP is successful when a real WooCommerce store administrator can:

1. Configure product COGS.
2. Record relevant direct order costs.
3. Open an order.
4. See its profit breakdown.
5. Understand whether the calculation is complete.
6. Identify loss-making orders.
7. View profitability for a date range.
8. Export profitability data.

The workflow must be understandable without accounting expertise.

---

## 33. Development priority

The primary development rule is:

`Correctness > Fancy Features`

A simple financial report with trustworthy numbers is more valuable than a visually impressive dashboard with unreliable calculations.

---

## 34. Initial development roadmap

### Task 0
Initialize repository.

### Task 1
Define the product specification.

### Task 2
Bootstrap the WordPress plugin.

### Task 3
Create the WooCommerce compatibility layer.

### Task 4
Design the financial domain model.

### Task 5
Implement the profit calculator.

### Task 6
Persist profit snapshots.

### Task 7
Integrate profitability into the WooCommerce order screen.

### Task 8
Build profitability reports.

### Task 9
Implement Margin Guard.

### Task 10
Add automated tests and quality tooling.

### Task 11
Prepare the first marketplace release.