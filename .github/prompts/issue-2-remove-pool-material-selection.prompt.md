---
description: "Remove pool material selection from Estimate UI, keeping a single material read from the database and displayed as a label"
---
# Issue #2 — Remove Pool Material Selection

## Goal

Remove the pool material dropdown from the Estimate form UI. Only one material ("Concrete Underground") should remain. Display it as a read-only label instead of a selectable dropdown, while keeping the value dynamic from the database for future extensibility.

## Changes Required

1. **estimate.php** — Replace the `<select>` dropdown for pool material with:
   - A hidden `<input>` carrying the DB value (`$estimate['pool_material']`)
   - A `.form-value-label` div showing the pricing label from the `pricing` table

2. **assets/js/app.js** — In `getFormData()`, read `pool_material` from the hidden input element instead of a `<select>`

3. **print-estimate.php** — Display the material from `$estimate['pool_material']` (read from DB, not hardcoded)

4. **assets/css/style.css** — Add `.form-value-label` class to style the read-only label consistently with form inputs

5. **Settings/pricing** — No changes. All `shell_*` pricing entries remain editable so new materials can be added in the future.

## Notes

- The `pool_material` column stays in the database schema unchanged
- Interior finish logic (`if pool_material === 'concrete'`) remains intact
- To re-enable material selection in the future, swap the hidden input + label back to a `<select>` populated from the pricing table
