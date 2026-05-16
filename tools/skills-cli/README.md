# Skills CLI v1.0.0

A specialized CLI tool for managing Enterprise ERP agent skills. This tool allows you to fetch standardized skill sets from remote repositories.

## Installation

You can run this tool directly using `npx`:

```bash
npx skills add pphatdev/erp-prompt
```

*Note: The `skills` package must be published to your registry or linked locally.*

## Local Development

If you want to run it locally or prepare for publishing:

1. Install dependencies:
   ```bash
   npm install
   ```

2. Link the command (optional):
   ```bash
   npm link
   ```

## Usage

### Add Skills
Fetch the `skills` directory from a GitHub repository and merge it into your local project.

```bash
npx skills add <user>/<repo>
```

Example:
```bash
npx skills add pphatdev/erp-prompt
```

### Options
- `-f, --force`: Overwrite existing files in the `./skills` directory.

## How it works
The CLI uses `degit` to perform a fast, git-less clone of the `skills` subdirectory from the specified repository. It ensures that only the relevant agent rules and standards are imported without the overhead of a full git history.
