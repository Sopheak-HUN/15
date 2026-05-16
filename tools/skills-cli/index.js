#!/usr/bin/env node

import { Command } from 'commander';
import degit from 'degit';
import fs from 'fs-extra';
import path from 'path';
import chalk from 'chalk';
import ora from 'ora';

const program = new Command();

/**
 * @description Enterprise ERP Skills Management CLI
 * @version 1.0.0
 * @command add
 * @param { String } repo Repository identifier (e.g., pphatdev/erp-prompt)
 * @option { Boolean } force Overwrite existing skills
 */
program
    .name('skills')
    .description('Enterprise ERP Skills Management CLI')
    .version('1.0.0');

program
    .command('add')
    .description('Add skills from a repository')
    .argument('<repo>', 'Repository identifier (e.g., pphatdev/erp-prompt)')
    .option('-f, --force', 'Overwrite existing skills', false)
    .action(async (repo, options) => {
        const spinner = ora(`Fetching skills from ${chalk.cyan(repo)}...`).start();

        try {
            const skillsDir = path.join(process.cwd(), 'agents/skills');
            const rulesDir = path.join(process.cwd(), 'agents/rules');

            // If folders exist and not forced, ask or handle
            if (!options.force && (fs.existsSync(skillsDir) || fs.existsSync(rulesDir))) {
                spinner.warn(chalk.yellow('Skills or Rules directory already exists. Use -f to overwrite.'));
                process.exit(1);
            }

            // Fetch Skills
            const skillsEmitter = degit(`${repo}/skills`, {
                cache: false,
                force: true,
                verbose: true,
            });

            // Fetch Rules
            const rulesEmitter = degit(`${repo}/rules`, {
                cache: false,
                force: true,
                verbose: true,
            });

            spinner.text = `Fetching skills from ${chalk.cyan(repo)}...`;
            await skillsEmitter.clone(skillsDir);

            spinner.text = `Fetching rules from ${chalk.cyan(repo)}...`;
            await rulesEmitter.clone(rulesDir);

            spinner.succeed(chalk.green(`Successfully added skills and rules to ${chalk.bold('./agents')}`));
            console.log(`\n${chalk.blue('Next steps:')}`);
            console.log(`1. Review the new skills in ${chalk.cyan('./agents/skills')}`);
            console.log(`2. Review the new rules in ${chalk.cyan('./agents/rules')}`);
            console.log(`3. Update your ${chalk.bold('AGENTS.md')} if necessary`);

        } catch (err) {
            spinner.fail(chalk.red('Failed to fetch skills or rules'));
            console.error(chalk.dim(err.message));
            process.exit(1);
        }
    });

program.parse();
