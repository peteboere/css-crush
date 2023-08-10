/**
 * @param {string} file - CSS file path
 * @param {CSSCrushOptions} [options]
 * @returns {CSSCrushProcess}
 */
export function watch(file: string, options?: CSSCrushOptions): CSSCrushProcess;
/**
 * @param {string} file - CSS file path
 * @param {CSSCrushOptions} [options]
 * @returns {Promise<string | boolean>}
 */
export function file(file: string, options?: CSSCrushOptions): Promise<string | boolean>;
/**
 * @param {string} string - CSS text
 * @param {CSSCrushOptions} [options]
 * @returns {Promise<string | boolean>}
 */
export function string(string: string, options?: CSSCrushOptions): Promise<string | boolean>;
declare namespace _default {
    export { watch };
    export { file };
    export { string };
}
export default _default;
export type CSSCrushOptions = {
    sourceMap?: boolean;
    boilerplate?: boolean;
    minify?: boolean;
    vendorTarget?: ('all' | 'none' | 'moz' | 'ms' | 'webkit');
    plugins?: string | [string];
    importPath?: string | [string];
    newlines?: ('use-platform' | 'windows' | 'unix');
    formatter?: ('block' | 'single-line' | 'padded');
    input?: string;
    context?: string;
    output?: string;
    vars?: object;
};
export type CSSCrushProcessOptions = CSSCrushOptions & {
    stdIn?: string;
    watch?: boolean;
};
/**
 * @typedef {object} CSSCrushOptions
 * @property {boolean} [sourceMap]
 * @property {boolean} [boilerplate]
 * @property {boolean} [minify=true]
 * @property {('all' | 'none' | 'moz' | 'ms' | 'webkit')} [vendorTarget='all']
 * @property {string | [string]} [plugins]
 * @property {string | [string]} [importPath]
 * @property {('use-platform' | 'windows' | 'unix')} [newlines='use-platform']
 * @property {('block' | 'single-line' | 'padded')} [formatter]
 * @property {string} [input]
 * @property {string} [context]
 * @property {string} [output]
 * @property {object} [vars]
 */
/**
 * @typedef {CSSCrushOptions & {
 *     stdIn?: string;
 *     watch?: boolean;
 * }} CSSCrushProcessOptions
 */
declare class CSSCrushProcess extends EventEmitter {
    /**
     * @param {CSSCrushProcessOptions} options
     * @returns {Promise<string | boolean>}
     */
    exec(options: CSSCrushProcessOptions): Promise<string | boolean>;
    /**
     * @param {CSSCrushProcessOptions} options
     * @returns {CSSCrushProcess}
     */
    watch(options: CSSCrushProcessOptions): CSSCrushProcess;
    kill(): void;
    #private;
}
import { EventEmitter } from 'node:events';
