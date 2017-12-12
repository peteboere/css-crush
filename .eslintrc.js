module.exports = {
    "env": {
        "es6": true,
        "node": true
    },
    "parserOptions": {
        "ecmaVersion": 8,
        "ecmaFeatures": {
            "experimentalObjectRestSpread": true,
        },
    },
    "extends": "eslint:recommended",
    "rules": {
        "curly": 2,
        "yoda": 2,
        "no-console": 0,
        "no-var": 2,
        "no-tabs": 2,
        "no-trailing-spaces": 2,
        "no-dupe-keys": 2,
        "no-else-return": 2,
        "no-useless-call": 2,
        "no-useless-return": 2,
        "no-control-regex": 0,
        "no-unused-expressions": [2, {"allowShortCircuit": true}],
        "no-lonely-if": 2,
        "arrow-parens": [2, "as-needed"],
        "arrow-spacing": 2,
        "object-shorthand": [2, "properties"],
        "no-empty": [2, {
            "allowEmptyCatch": true
        }],
        "eol-last": [2, "always"],
        "no-multiple-empty-lines": [2, {"max": 2, "maxEOF": 1, "maxBOF": 1}],
        "quotes": [2, "single", {"avoidEscape": true, "allowTemplateLiterals": true}],
        "keyword-spacing": 2,
        "space-before-blocks": 2,
        "linebreak-style": [2, "unix"],
        "object-curly-spacing": [2, "never"],
        "array-bracket-spacing": [2, "never"],
        "brace-style": [2, "stroustrup"],
        "semi": [2, "always"],
        "quote-props": [2, "as-needed"],
        "comma-dangle": [2, {
            "objects": "always-multiline",
            "functions": "never",
        }],
        "comma-spacing": [2, {
            "before": false,
            "after": true
        }],
        "key-spacing": [2, {
            "beforeColon": false,
            "afterColon": true
        }],
        "space-before-function-paren": [2, {
            "anonymous": "always",
            "asyncArrow": "always",
            "named": "never"
        }],
    }
};
