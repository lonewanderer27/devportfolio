# Document seed fixtures

These fixtures exercise PDF, PowerPoint, Excel and Word document previews,
including portrait and landscape pages, hidden sheets, legacy formats and corrupt
uploads. The readable content follows the portfolio's seeded services, tools and
metrics: social media marketing, digital marketing, web creation, content calendars,
analytics, WordPress support, +5 years' experience, +40 happy clients and a 24-hour
typical reply time. Client names and financial results are intentionally omitted.

Regenerate them inside DDEV:

```bash
ddev exec python3 wp-content/plugins/custom-post-types-dp/bin/make-document-fixtures.py
```

Use `--large` to create the ignored timeout-test files in `generated/`.
