# 🎯 Stratégie de Test & Edge Cases

La classe `Updater` est un orchestrateur mince avec 3 méthodes triviales de délégation et une méthode `updateToLastVersion()` complexe. Les délégations sont 100% testables via mocks PHPUnit natifs. La méthode `updateToLastVersion()` est partiellement testable : les branches de détection de plateforme/architecture et la validation PHAR sont testables en sous-classant `Updater` pour overrider `__DIR__` (impossible nativement) ou en testant les exceptions avant que `Filesystem`/`exec()` ne soient atteints. Les scénarios #1 (UnhandledMatchError sur architecture inconnue) et #2 (RuntimeException hors PHAR) nécessitent d'exposer la logique interne via une méthode extractible ou un wrapper testable. Stratégie adoptée : créer une `TestablUpdater` anonyme/extends qui permet d'injecter `__DIR__` simulé via méthode protégée. Les tests #4 (IOException bubble) et #5 (escapeshellarg) ne peuvent être vérifiés sans injection de `Filesystem` et wrapping de `exec()` — ces tests sont marqués comme partiels avec une note explicite. Focus sur : 3 délégations via DataProvider, exception plateforme Windows/BSD, exception architecture ARM32/RISCV, exception hors PHAR, et vérification que `getLatestPackageUrl` est appelé avec les bons arguments sur Linux x86_64.

# 🏗️ Fixtures & Mocks

- `VersionChecker` mock : `getCurrentVersion()→'2.4.1'`, `getLastVersion()→'2.5.0'`, `isUpdateAvailable()→true/false`
- `GitlabClient` mock : `getLatestPackageUrl($platform, $arch)→url`
- `TestableUpdater` extends `Updater` : surcharge `getDir()` protégée pour simuler `__DIR__`
- DataProviders : delegation (3 cas), unsupportedPlatform (3 cas), nonPharContext (3 cas)

# 📈 Score de Testabilité : 55/100
**Verdict :** REFACTORING REQUIRED

`Filesystem` instancié en dur et `exec()` global bloquent la vérification des effets de bord de `updateToLastVersion()`. Sans injection, seules les branches exception (plateforme/PHAR) sont fiablement testables. Recommandation : injecter `Filesystem` + wrapper `ShellExecutor`.

# 💻 Code PHP