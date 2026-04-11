<!--
MAINTENANCE: Update this file when:
- Adding/removing composer scripts
- Changing the directory structure (new modules, major refactors)
- Modifying build/test workflows
- Adding new architectural patterns or conventions
-->

# AGENTS.md - SQLite Database Integration

## Project overview
This project implements SQLite database support for MySQL-based projects.

It is a monorepo that includes the following components:
- **MySQL lexer** — A fast MySQL lexer with multi-version support.
- **MySQL parser** — An exhaustive MySQL parser with multi-version support.
- **SQLite driver** — A MySQL emulation layer on top of SQLite with a PDO-compatible API.
- **MySQL proxy** — A MySQL binary protocol implementation to support MySQL-based projects beyond PHP.
- **WordPress plugin** — A plugin that adds SQLite support to WordPress.
- **Test suites** — A set of extensive test suites to cover MySQL syntax and functionality.

The monorepo packages are placed under the `packages` directory.

The WordPress plugin links the SQLite driver using a symlink. The build script
replaces the symlink with a copy of the driver for release.

The codebase is pure PHP with zero dependencies. It supports PHP 7.2 through 8.5,
MySQL syntax from version 5.7 onward, and requires SQLite 3.37.0 or newer
(with legacy mode down to 3.27.0).

### New and old driver
At the moment, the project includes two MySQL-on-SQLite driver implementations:
1. A new, AST-based MySQL-on-SQLite driver (`class-wp-pdo-mysql-on-sqlite.php`).
2. A legacy, token-based MySQL-to-SQLite translator (`class-wp-sqlite-translator.php`).

This state is temporary. The new driver will fully replace the legacy one. New features
must always be implemented in the new driver. The legacy driver can receive small fixes.
The new driver is under a `WP_SQLITE_AST_DRIVER` feature flag, but it is widely used.

## Commands
The codebase is written in PHP and Composer is used to manage the project.
The following commands are useful for development and testing:

```bash
composer install                        # Install dependencies
composer run check-cs                   # Check coding standards (PHPCS)
composer run fix-cs                     # Auto-fix coding standards (PHPCBF)
composer run build-sqlite-plugin-zip    # Build the plugin zip
composer run prepare-release            # Prepare a new release

# SQLite driver tests (under packages/mysql-on-sqlite)
cd packages/mysql-on-sqlite
composer run test                       # Run unit tests
composer run test tests/SomeTest.php    # Run specific unit test file
composer run test -- --filter testName  # Run specific unit test class/method

# SQLite Database Integration plugin tests
composer run test                       # Run unit tests
composer run test tests/SomeTest.php    # Run specific unit test file
composer run test -- --filter testName  # Run specific unit test class/method
composer run test-e2e                   # Run E2E tests (Playwright via WP env)

# WordPress tests
composer run wp-setup                   # Set up WordPress with SQLite for tests
composer run wp-run                     # Run a WordPress repository command
composer run wp-test-start              # Start WordPress environment (Docker)
composer run wp-test-php                # Run WordPress PHPUnit tests
composer run wp-test-e2e                # Run WordPress E2E tests (Playwright)
composer run wp-test-clean              # Clean up WordPress environment (Docker and DB)
```

## Release workflow
Release is streamlined with a local preparation script and GitHub Actions:

1. **Run the release preparation script locally.**
   ```bash
   composer run prepare-release <version>
   ```
   The script will:
     - Bump version numbers and generate a changelog from merged PRs.
     - Create a `release/<version>` branch with a preparation commit.
     - Push the branch and create a PR.

2. **Review the PR.**
   Edit the changelog or push additional changes to the release branch.

3. **Mark as ready and merge the PR.**
   The `release-publish` workflow will automatically:
     - Build the plugin ZIP.
     - Create and publish a GitHub release with the ZIP attached.
     - Deploy the release to WordPress.org.

## Architecture
The project consists of multiple components providing different APIs that funnel
into the SQLite driver to support diverse use cases both inside and outside the
PHP ecosystem.

### Component overview
The following diagrams show how different types of applications can be supported
using components from this project.

**PHP applications** are supported through a PDO\MySQL-compatible API:
```
PHP applications, Adminer, phpMyAdmin
  ↓ PDO\MySQL API
SQLite driver
  ↓ PDO\SQLite
SQLite
```

**WordPress** projects are powered by a `wpdb` compatible drop-in:
```
WordPress + plugins, WordPress Playground, WordPress Studio, wp-env
  ↓ wpdb
wpdb drop-in
  ↓ PDO\MySQL API
SQLite driver
  ↓ PDO\SQLite
SQLite
```

**Other applications** can be run using the MySQL proxy:
```
MySQL CLI, Desktop clients
  ↓ MySQL binary protocol v10
MySQL proxy
  ↓ PDO\MySQL API
SQLite driver
  ↓ PDO\SQLite
SQLite
```

### Query processing pipeline
The following diagram illustrates how a MySQL query is processed and emulated:

```
MySQL query
  ↓ string
Lexer
  ↓ tokens
Parser
  ↓ AST
Translation & Emulation ← INFORMATION_SCHEMA emulation
  ↓ SQL
SQLite
```

## Principles
This project implements sophisticated emulation and complex APIs. Any changes and
new features must be carefully designed and planned, and their implementation
must fit into the project architecture.

When working on changes to the project:
- **Analyze** the existing code and its architecture.
- **Design** changes in accordance with the existing architecture, if possible.
- **Modify** or extend the architecture with consideration when appropriate.
- **Plan** the implementation carefully so that the changes align with the project.
- **Implement** the changes following the planned design and architecture.
- **Test** all newly added logic using a test suite that is appropriate.
- **Verify** implemented changes. Review their architecture and its suitability.
- **Adjust** the implemented changes if needed to improve the implementation.
- **Simplify** the implemented changes when possible to keep them lean.

Always aim to implement changes and solve problems fully and properly. Don't use
shortcuts and hacks. Never silence errors, linters, or tests to simplify the job.

## Security
Security is critical to this project. The implementation must prevent all vulnerabilities
that could lead to data compromise or corruption. This includes:
- **Quoting and escaping:** Always use correct escaping and quoting that's appropriate
  in a given context. Always correctly prevent SQL injection and other vulnerabilities.
- **Encoding:** Be diligent about encodings and the nuances between MySQL and SQLite.
- **Data integrity:** Always preserve data integrity to avoid data loss or corruption.

Always scrutinize implemented logic for security vulnerabilities and verify any
assumptions. Never take shortcuts.

## Compatibility
This project must support a range of PHP and SQLite versions, and it must evolve
the public APIs responsibly, following semantic versioning practices.

In particular:
- **Public APIs:** It's possible to evolve the public API, but this must always be
  surfaced to the developer so versioning decisions can be made.
- **PDO API:** The SQLite driver must follow PDO\MySQL API as closely as possible.
- **MySQL binary protocol:** The MySQL proxy must follow the MySQL binary protocol
  as closely as possible.
- **PHP version support:** All PHP versions starting from **PHP 7.2** must be supported.
  It is possible to use PHP version checks when needed.
- **SQLite version support:** All SQLite versions starting from **SQLite 3.37.0** must be
  supported. Older versions (down to 3.27.0) have limited compatibility and require setting
  `WP_SQLITE_UNSAFE_ENABLE_UNSUPPORTED_VERSIONS`.

## Coding conventions
Follow these conventions when writing code in this project:
- **Coding style:** Use WordPress Coding Standards via PHPCS (`phpcs.xml.dist`).
- **Function ordering:** First caller, then callee. When function A calls function B, write first A, then B.
- **Method ordering:** First public, then protected, then private. Respect Function ordering as well.

## Git
When creating commits, branches, and pull requests, use clear, concise, and
human-readable prose in plain English.

### Commits
- Make commits readable for humans, not machines.
- Make subject of a commit message short but clear.
- Start with a verb, use present-tense, imperative form.
- Explain details in a commit body below, if needed.
- Include links in the body if the change relates to external sources, issues, PRs, or tickets.

### Pull requests
- When creating a pull request, always follow the repository PR template.
- Pull request title must be brief and accurate.
- Pull request description must be clear, comprehensible, and well organized.
