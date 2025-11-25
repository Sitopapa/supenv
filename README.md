# 🔒 Supenv - Secure PHP Env Manager

[![Tests](https://github.com/sitopapa/supenv/workflows/Tests/badge.svg)](https://github.com/sitopapa/supenv/actions)
[![Code Quality](https://github.com/sitopapa/supenv/workflows/Code%20Quality/badge.svg)](https://github.com/sitopapa/supenv/actions)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

**Supenv** is a zero-dependency, production-ready tool to manage, secure, and validate your `.env` files. Use it as a robust **CLI Tool** or integrate it directly into your PHP application as a **Library**.

It uses **Sodium** encryption (industry standard) to keep your secrets safe.

## 🚀 Features

- **Zero Dependency:** No external packages required. Lightweight and fast.
- **🔒 Encryption:** Encrypt `.env` to `.env.enc` using Sodium for safe repository storage.
- **🛡️ Type Safety:** Retrieve values as `bool`, `int`, or `string` easily in your code.
- **✅ Validation:** Compare `.env` against `.env.example` to find missing keys (Perfect for CI/CD).
- **🔄 Key Rotation:** Safely rotate your encryption keys without data loss.
- **📦 Auto-Backup:** Automatically creates `.env.bak` before any modification.
- **🙈 Masking:** `list` command masks sensitive data like `API_KEY` or `PASSWORD`.
- **🧪 Fully Tested:** 41 tests with ~95% code coverage.
- **🚀 CI/CD Ready:** Automated testing with GitHub Actions.
- **⚠️ Custom Exceptions:** Type-safe error handling for better debugging.

---

## 📦 Installation

```bash
composer require sitopapa/supenv
```

**Requirements:**
- PHP 8.0 or higher
- ext-sodium extension (usually enabled by default)

---

## 💻 CLI Usage

Supenv comes with a built-in terminal command.

### 1. Direct Usage

```bash
# Linux / Mac
vendor/bin/supenv list
vendor/bin/supenv help

# Windows
vendor\bin\supenv list
vendor\bin\supenv help
```

### 2. Composer Script (Recommended)

Add this to your `composer.json`:

```json
"scripts": {
    "supenv": "supenv"
}
```

Now run:

```bash
composer supenv list
composer supenv help
```

### CLI Commands

| Command  | Description | Example |
|----------|-------------|---------|
| **encrypt** | Encrypts `.env` → `.env.enc` & generates key | `composer supenv encrypt` |
| **decrypt** | Decrypts `.env.enc` → `.env` | `composer supenv decrypt` |
| **list** | Lists all variables (masks sensitive data) | `composer supenv list` |
| **get** | Gets a specific value (unmasked) | `composer supenv get DB_PASSWORD` |
| **set** | Sets or updates a value | `composer supenv set APP_DEBUG true` |
| **unset** | Removes a variable | `composer supenv unset APP_DEBUG` |
| **rotate** | Rotates encryption key safely | `composer supenv rotate` |
| **validate** | Checks missing keys vs `.env.example` | `composer supenv validate` |
| **example** | Generates `.env.example` from `.env` | `composer supenv example` |

---

## 🔌 Library Usage (PHP)

```php
use Sitopapa\Supenv\Supenv;

// Initialize
$env = new Supenv(__DIR__ . '/.env');
$env->load();

// --- Reading Data ---
$debug = $env->getBool('APP_DEBUG'); // true/false
$port  = $env->getInt('DB_PORT');    // int 3306
$key   = $env->get('API_KEY');       // string

// --- Writing Data ---
$env->set('DB_HOST', '127.0.0.1');

$env->setMany([
    'APP_ENV' => 'production',
    'CACHE_DRIVER' => 'redis'
]);

// Save changes
$env->save();

// --- Encryption ---
$env->encrypt();          // creates .env.enc
$env->decrypt('.env.enc'); // restores .env

// --- Validation ---
$env->require(['DB_HOST', 'DB_PASSWORD', 'API_KEY']); // Throws ValidationException if missing
```

---

## ⚠️ Exception Handling

Supenv uses custom exceptions for better error handling:

```php
use Sitopapa\Supenv\Supenv;
use Sitopapa\Supenv\Exceptions\ValidationException;
use Sitopapa\Supenv\Exceptions\FileNotFoundException;
use Sitopapa\Supenv\Exceptions\DecryptionException;

try {
    $env = new Supenv('.env');
    $env->load();
    $env->require(['DB_HOST', 'DB_PASSWORD']);
} catch (ValidationException $e) {
    // Get missing keys
    $missing = $e->getMissingKeys();
    echo "Missing keys: " . implode(', ', $missing);
} catch (FileNotFoundException $e) {
    echo "File not found: " . $e->getMessage();
} catch (DecryptionException $e) {
    echo "Decryption failed: " . $e->getMessage();
}
```

**Available Exceptions:**
- `SupenvException` - Base exception (all Supenv exceptions extend this)
- `FileNotFoundException` - When a required file is not found
- `EncryptionException` - When encryption fails
- `DecryptionException` - When decryption fails
- `ValidationException` - When validation fails (has `getMissingKeys()` method)
- `SecurityException` - When a security violation is detected

---

## 🧪 Testing

Supenv comes with a comprehensive test suite:

```bash
# Run all tests
composer test

# Run tests with coverage report
composer test:coverage

# Run specific test
composer test:filter testEncryptionWorks
```

**Test Stats:**
- ✅ 41 tests
- ✅ 92 assertions
- ✅ ~95% code coverage

---

## 🔒 Security Best Practices

**What to commit:**
- ✅ `.env.enc` (encrypted environment file)
- ✅ `.env.example` (template without values)
- ✅ `.env.key` **ONLY on local development**

**What to NEVER commit:**
- ❌ `.env` (actual environment file with secrets)
- ❌ `.env.bak` (backup file)
- ❌ `.env.key` (encryption key in production)

**Production Deployment:**
1. Commit `.env.enc` to repository
2. Upload `.env.key` to server manually (via SSH, secure file transfer, or secrets manager)
3. Run `supenv decrypt` on server to restore `.env`

### Recommended `.gitignore`

```gitignore
.env
.env.bak
.env.key
```

---

## 📚 Documentation

- [CHANGELOG.md](CHANGELOG.md) - Version history and changes
- [ROADMAP.md](ROADMAP.md) - Planned features and improvements
- [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md) - Recent implementation details

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

**Development:**

```bash
# Clone the repository
git clone https://github.com/sitopapa/supenv.git
cd supenv

# Install dependencies
composer install

# Run tests
composer test

# Run tests with coverage
composer test:coverage
```

---

## 🚀 CI/CD

Supenv uses GitHub Actions for automated testing:

- ✅ Tests run on **PHP 8.0, 8.1, 8.2, 8.3**
- ✅ Cross-platform testing (**Ubuntu**, **Windows**, **macOS**)
- ✅ Automatic code quality checks
- ✅ Composer validation

---

## 📜 License

MIT License - see [LICENSE](LICENSE) file for details.

---

## 🌟 Credits

Created and maintained by [Veli GEÇGEL](https://github.com/sitopapa)

---

## 💡 Support

If you find this package useful, please consider:
- ⭐ Starring the repository
- 🐛 Reporting bugs
- 💡 Suggesting new features
- 🤝 Contributing to the code

---

**Made with ❤️ in Turkey**
