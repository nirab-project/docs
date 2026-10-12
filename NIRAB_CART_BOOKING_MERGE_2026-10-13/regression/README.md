# NIRAB — isolated regression tests

These checks operate on source files and isolated stubs. They DO NOT connect to Laravel HTTP, MySQL, Docker, Flutter, Odoo, or payment gateways.

Unpack the source ZIPs as sibling directories named `01-NIRAB-BACKEND-SOURCE`, `02-NIRAB-CUSTOMER-SOURCE`, `03-NIRAB-PROVIDER-SOURCE`, and `04-NIRAB-SERVICEMAN-SOURCE`. Point `NIRAB_SOURCE_ROOT` to their parent.

```bash
NIRAB_SOURCE_ROOT=/path/to/sources php isolated_security_contract_test.php
NIRAB_SOURCE_ROOT=/path/to/sources python source_contracts_test.py
```

With the patched files applied, expected results: **PHP 10/10**, **source contracts 25/25**. The original imported ZIP source fails intentionally; failures before applying patches are recorded in `baseline_*_results.txt`.

For real confidence, run Laravel + MySQL integration and Flutter browser/physical phone tests in your own environment. `backend_*_php_lint.txt` contains PHP 8.4 static syntax results; there is no actual PHPUnit suite execution in this package.
