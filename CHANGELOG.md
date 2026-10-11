# Changelog

## 1.1.1

This NhanAZ-Libraries fork keeps the upstream GPL-3.0 license and public parser interfaces. It can be pinned as a virion source by a consuming plugin. The upstream Composer package remains separate.

- Fix virion shading and strict inequality handling in Calculator.
- Preserve evaluation order, arithmetic exceptions and result types in the documented optimizer cases. Remove automatic floating-point regrouping.
- Reject malformed empty operands and misplaced separators with parser errors.
- Keep the optimizer changes covered by PHPUnit and Calculator expression fixtures. PHPStan runs at maximum level with two documented, exact-line exceptions for recursive native-array mutations in `Parser`.

The fork CI validates Composer metadata, installs locked dependencies, runs 150 PHPUnit tests with 606 assertions and analyzes source at PHPStan maximum level. Calculator pins the fork source, builds a standalone PHAR with DevTools and has passed console and connected-client arithmetic, boolean and variable-chat checks on one Axolotl-PM 5.49.1 release. These checks do not prove compatibility with every Axolotl-PM 5.x patch or every possible expression.

For a rollback, pin the earlier fork commit in the consuming plugin, rebuild its PHAR and redeploy the previous verified artifact. Keep the previous PHAR until the replacement has passed the same runtime checks. No stored Calculator data is migrated by this library release.
