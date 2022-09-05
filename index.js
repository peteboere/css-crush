/*eslint no-control-regex: 0*/
import os from 'os';
import fs from 'fs';
import pathUtil from 'path';
import {fileURLToPath} from 'url';
import querystring from 'querystring';
import {EventEmitter} from 'events';
import {exec} from 'child_process';
import {createHash} from 'crypto';
import glob from 'glob';

const cliPath = pathUtil
    .resolve(pathUtil
        .dirname(fileURLToPath(import.meta.url)), './cli.php');

const processes = [];
const processExec = (...args) => {
    processes.push(exec(...args));
    return processes.at(-1);
};
const exit = () => {
    let proc;
    while ((proc = processes.pop())) {
        proc?.kill();
    }
    process.exit();
};

for (const event of ['exit', 'SIGINT', 'SIGUSR1', 'SIGUSR2', 'uncaughtException', 'SIGTERM']) {
    process.on(event, exit);
}

class Process extends EventEmitter {

    exec(options) {
        return new Promise(resolve => {
            let command = this.assembleCommand(options);
            const {stdIn} = options;
            if (stdIn) {
                command = `echo '${stdIn.replace(/'/g, "\\'")}' | ${command}`;
            }
            processExec(command, (error, stdout, stderr) => {
                process.stderr.write(stderr.toString());
                if (error) {
                    return resolve(false);
                }
                const stdOut = stdout.toString();
                if (stdIn) {
                    process.stdout.write(stdOut);
                }
                return resolve(stdOut || true);
            });
        });
    }

    watch(options) {
        options.watch = true;
        const command = this.assembleCommand(options);
        const proc = processExec(command);

        /*
         * Emitting 'error' events from EventEmitter without
         * any error listener will throw uncaught exception.
         */
        this.on('error', () => {});

        proc.stderr.on('data', msg => {
            msg = msg.toString();
            process.stderr.write(msg);
            msg = msg.replace(/\x1B\[[^m]*m/g, '').trim();

            const [, signal, detail] = /^([A-Z]+):\s*(.+)/i.exec(msg) || [];
            const {input, output} = options;
            const eventData = {
                signal,
                options: {
                    input: input ? pathUtil.resolve(input) : null,
                    output: output ? pathUtil.resolve(output) : null,
                },
            };

            if (/^(WARNING|ERROR)$/.test(signal)) {
                const error = new Error(detail);
                Object.assign(error, eventData, {severity: signal.toLowerCase()});
                this.emit('error', error);
            }
            else {
                this.emit('data', {message: detail, ...eventData});
            }
        });

        proc.on('exit', exit);

        return this;
    }

    assembleCommand(options) {
        return `${process.env.CSSCRUSH_PHP_BIN || 'php'} ${cliPath} ${this.stringifyOptions(options)}`;
    }

    stringifyOptions(options) {
        const args = [];
        options = {...options};
        for (let name in options) {
            // Normalize to hypenated case.
            const cssCase = name.replace(/[A-Z]/g, m => `-${m.toLowerCase()}`);
            if (name !== cssCase) {
                options[cssCase] = options[name];
                delete options[name];
                name = cssCase;
            }
            let value = options[name];
            switch (name) {
                // Booleans.
                case 'watch':
                case 'source-map':
                case 'boilerplate':
                    if (value) {
                        args.push(`--${name}`);
                    }
                    break;
                case 'minify':
                    if (! value) {
                        args.push(`--pretty`);
                    }
                    break;
                // Array/list values.
                case 'vendor-target':
                case 'plugins':
                case 'import-path':
                    if (value) {
                        value = (Array.isArray(value) ? value : [value]).join(',');
                        args.push(`--${name}="${value}"`);
                    }
                    break;
                // String values.
                case 'newlines':
                case 'formatter':
                case 'input':
                case 'context':
                case 'output':
                    if (value) {
                        args.push(`--${name}="${value}"`);
                    }
                    break;
                case 'vars':
                    args.push(`--${name}="${querystring.stringify(value)}"`);
                    break;
            }
        }

        return args.join(' ');
    }
}

export default {
    watch,
    file,
    string,
};

export function watch(file, options={}) {
    ({file: options.input, context: options.context} = resolveFile(file, {watch: true}));
    return (new Process()).watch(options);
}

export function file(file, options={}) {
    ({file: options.input, context: options.context} = resolveFile(file));
    return (new Process()).exec(options);
}

export function string(string, options={}) {
    options.stdIn = string;
    return (new Process()).exec(options);
}

const resolveFile = (input, {watch}={}) => {

    if (Array.isArray(input)) {

        let initial;
        let previous;

        /*
         * Generate temporary file containing entrypoints.
         * Poll to update on additions and deletions.
         */
        const poller = () => {
            const result = resolveInputs(input);

            if (result.fingerprint !== previous?.fingerprint) {
                fs.writeFileSync(initial?.file || result.file, result.content, {
                    mode: 0o777,
                });
            }

            initial ||= result;
            previous = result;

            if (watch) {
                setTimeout(poller, 2000);
            }

            return result;
        };

        return poller();
    }

    return {
        file: input,
    };
};

const resolveInputs = fileGlobs => {

    const result = {};

    let files = new Set();
    for (const it of fileGlobs) {
        for (const path of (glob.sync(it) || []).sort()) {
            files.add(path);
        }
    }

    if (! files.size) {
        return result;
    }

    files = [...files];

    const rootPath = files
        .shift();
    const context = pathUtil
        .dirname(rootPath);
    const rootFile = pathUtil
        .basename(rootPath);

    const content = [rootFile]
        .concat(files
            .map(it => pathUtil
                .relative(context, it)))
        .map(it => `@import "./${it}";`)
        .join('\n');

    const fingerprint = createHash('md5')
        .update(content)
        .digest('hex');

    const outputDir = `${os.tmpdir()}/csscrush`;
    if (! fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, {
            mode: 0o777,
        });
    }

    return Object
        .assign(result, {
            context,
            content,
            fingerprint,
            file: `${outputDir}/${fingerprint}.css`,
        });
};
