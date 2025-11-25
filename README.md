# 🔒 Supenv - Secure PHP Env Manager
**Supenv** is a zero-dependency, powerful tool to manage, secure, and validate your `.env` files. Use it as a robust **CLI Tool** or integrate it directly into your PHP application as a **Library**.

It uses **Sodium** encryption (industry standard) to keep your secrets safe.

## 🚀 Features
- **Zero Dependency:** No external packages required. Lightweight and fast.
- **🔒 Encryption:** Encrypt `.env` to `.env.enc` using Sodium for safe repository storage.
- **🛡️ Type Safety:** Retrieve values as `bool`, `int`, or `string` easily in your code.
- **✅ Validation:** Compare `.env` against `.env.example` to find missing keys (Perfect for CI/CD).
- **🔄 Key Rotation:** Safely rotate your encryption keys without data loss.
- **📦 Auto-Backup:** Automatically creates `.env.bak` before any modification.
- **🙈 Masking:** `list` command masks sensitive data like `API_KEY` or `PASSWORD`.

---

## 📦 Installation
```bash
composer require sitopapa/supenv
```
Requirement: PHP 8.0+ and ext-sodium extension must be enabled.

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
Add this to your composer.json:
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
| encrypt | Encrypts `.env` → `.env.enc` & generates key | composer supenv encrypt |
| decrypt | Decrypts `.env.enc` → `.env` | composer supenv decrypt |
| list | Lists all variables (masks sensitive data) | composer supenv list |
| get | Gets a specific value (unmasked) | composer supenv get DB_PASSWORD |
| set | Sets or updates a value | composer supenv set APP_DEBUG true |
| unset | Removes a variable | composer supenv unset APP_DEBUG |
| rotate | Rotates encryption key safely | composer supenv rotate |
| validate | Checks missing keys vs `.env.example` | composer supenv validate |
| example | Generates `.env.example` from `.env` | composer supenv example |

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
```

---

## 🔒 Security Best Practices
- Commit: `.env.enc` and `.env.example`
- **NEVER** commit: `.env` and `.env.key`
- Deploy: Upload `.env.key` to server manually

### .gitignore
```
.env
.env.bak
.env.key
```

---

## 📜 License
MIT License
