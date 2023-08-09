import assert from 'node:assert/strict';
import {describe, it} from 'node:test';
import {EventEmitter} from 'node:events';
import {writeFileSync, readFileSync} from 'node:fs';
import {tmpdir} from 'node:os';
import pathUtil from 'node:path';
import * as csscrush from "../index.js";

describe('csscrush.string', () => {

    it('should minify CSS text', async () => {

        const result = await csscrush
            .string('foo {color: #ff0000;}');

        assert.strictEqual(result, 'foo{color:#f00}\n');
    });
});

describe('csscrush.file', () => {

    it('should minify CSS text', async () => {

        const cssText = 'foo {color: #ff0000;}';

        const testFile = pathUtil
            .join(tmpdir(), 'test.css');

        writeFileSync(testFile, cssText);

        const result = await csscrush
            .file(testFile);

        assert(result, 'foo{color:#f00}\n');
    });
});

describe('csscrush.watch', {only: true}, () => {

    it('should minify CSS text', async () => {

        const cssText = 'foo {color: #ff0000;}';

        const testFile = pathUtil
            .join(tmpdir(), 'test.css');

        const testFileOutput = `${testFile}.result.css`;

        writeFileSync(testFile, cssText);

        const result = await csscrush
            .watch(testFile, {
                output: testFileOutput,
                boilerplate: false,
            });

        assert(result instanceof EventEmitter);

        setTimeout(() => {
            writeFileSync(testFile, 'foo {color: #ffffff;}');
        });

        const event = await new Promise((resolve, reject) => {
            result
                .on('error', error => {
                    reject(error);
                })
                .on('data', resolve);
        });

        assert.strictEqual(event?.signal, 'WRITE');

        const contents = readFileSync(testFileOutput)
            .toString();

        assert.strictEqual(contents, 'foo{color:#fff}');

        result.kill();
    });
});
