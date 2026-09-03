with open('app/GOAIEZ-MASTER-PLAN.md', 'a') as f:
    f.write("""
## X-163 `PriceBook` — CAPABILITY TABLE

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **N-062** | A price on site comes from X-163 | a price on site comes from X-163 or is refused | `price_book_items` | a price is generated | a price on site comes from X-163 or is refused | ⑥⑦ inherit · ⛔ **REFUSES with UNVERIFIED_PRICE** |
""")
