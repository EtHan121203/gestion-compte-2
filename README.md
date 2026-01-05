# gestion-compte-2

A Symfony 7.4 LTS project for account management.

## Requirements

- PHP 8.2 or higher
- Composer 2.x

## Installation

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```

## Usage

### Development Server

To run the development server:
```bash
php -S localhost:8000 -t public/
```

### Console Commands

List all available commands:
```bash
php bin/console list
```

Clear cache:
```bash
php bin/console cache:clear
```

### Project Information

View project information:
```bash
php bin/console about
```

## Project Structure

- `bin/` - Console executable
- `config/` - Configuration files
- `public/` - Web server document root
- `src/` - Application source code
- `var/` - Generated files (cache, logs)
- `vendor/` - Third-party dependencies (not tracked in Git)

## Technology Stack

- **Symfony**: 7.4.3 (LTS)
- **PHP**: 8.2+