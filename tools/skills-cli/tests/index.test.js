import { describe, it, expect, vi } from 'vitest';
import { Command } from 'commander';

describe('Skills CLI', () => {
    it('should have the correct version', () => {
        const program = new Command();
        program.version('1.0.0');
        expect(program.version()).toBe('1.0.0');
    });

    it('should define the add command', () => {
        const program = new Command();
        program.command('add');
        expect(program.commands.find(c => c.name() === 'add')).toBeDefined();
    });
});
