/*eslint sort-keys: 2*/
/*eslint object-property-newline: 2*/
/*eslint quote-props: [2, "consistent"]*/

module.exports = {
    env: {
        es6: true,
        node: true,
    },
    extends: 'eslint:recommended',
    parserOptions: {
        ecmaVersion: 9,
    },
    rules: {
        'array-bracket-spacing': [2, 'never'],
        'array-element-newline': [2, 'consistent'],
        'arrow-parens': [2, 'as-needed'],
        'arrow-spacing': 2,
        'brace-style': [2, 'stroustrup'],
        'camelcase': [2, {
            ignoreDestructuring: true,
            properties: 'never',
        }],
        'comma-dangle': [2, {
            arrays: 'always-multiline',
            functions: 'never',
            objects: 'always-multiline',
        }],
        'comma-spacing': [2, {
            after: true,
            before: false,
        }],
        'curly': 2,
        'eol-last': [2, 'always'],
        'eqeqeq': 2,
        'key-spacing': [2, {
            afterColon: true,
            beforeColon: false,
        }],
        'keyword-spacing': 2,
        'linebreak-style': [2, 'unix'],
        'multiline-comment-style': [2, 'starred-block'],
        'no-console': 2,
        'no-dupe-keys': 2,
        'no-else-return': 2,
        'no-empty': [2, {
            allowEmptyCatch: true,
        }],
        'no-lonely-if': 2,
        'no-multi-spaces': 2,
        'no-multiple-empty-lines': [2, {
            max: 2,
            maxBOF: 1,
            maxEOF: 1,
        }],
        'no-new-object': 2,
        'no-template-curly-in-string': 2,
        'no-tabs': 2,
        'no-throw-literal': 2,
        'no-trailing-spaces': 2,
        'no-unneeded-ternary': 2,
        'no-unused-expressions': [2, {allowShortCircuit: true}],
        'no-unused-vars': [2, {
            args: 'all',
            argsIgnorePattern: '^(req|res|next)$|^_',
            varsIgnorePattern: '^_$',
        }],
        'no-useless-call': 2,
        'no-useless-concat': 2,
        'no-useless-return': 2,
        'no-var': 2,
        'object-curly-newline': [2, {consistent: true}],
        'object-curly-spacing': [2, 'never'],
        'object-shorthand': [2, 'properties'],
        'operator-linebreak': [2, 'before', {
            overrides: {
                ':': 'ignore',
                '?': 'ignore',
            },
        }],
        'prefer-arrow-callback': 2,
        'prefer-const': [2, {destructuring: 'all'}],
        'prefer-destructuring': [2, {
            array: false,
            object: true,
        }],
        'prefer-object-spread': 2,
        'quote-props': [2, 'as-needed'],
        'quotes': [2, 'single', {
            allowTemplateLiterals: true,
            avoidEscape: true,
        }],
        'semi': [2, 'always'],
        'space-before-blocks': 2,
        'space-before-function-paren': [2, {
            anonymous: 'always',
            asyncArrow: 'always',
            named: 'never',
        }],
        'space-unary-ops': [2, {
            nonwords: false,
            overrides: {'!': true},
            words: true,
        }],
        'yoda': 2,
    },
};
