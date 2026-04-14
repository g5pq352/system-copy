const plugin = require('tailwindcss/plugin')

/* --- fluid font function --- */
function fluidFont(px, minRatio = 0.8) {
    const n = parseFloat(px)
    const min = n * minRatio
    return `clamp(${min}px, calc(${n}/19.2*1vw), ${n}px)`
}
/* --- vw function (外部可重複使用) --- */
function toVW(value) {
    const BASE_FONT_SIZE_PX = 16;
    const DESIGN_WIDTH_UNIT = 19.2; // 1920px / 100

    let px;
    let negative = false;

    // 處理負號：例如 "-5rem"、"-40"
    if (typeof value === 'string' && value.startsWith('-')) {
        negative = true;
        value = value.slice(1); // 去掉開頭的 '-'
    }

    if (typeof value === 'string' && value.endsWith('rem')) {
        const rem = parseFloat(value);
        if (isNaN(rem)) return value;

        px = rem * BASE_FONT_SIZE_PX;
    } else {
        px = parseFloat(value);
        if (isNaN(px)) return value;
    }

    // 套回負號：px 變成負數，塞進 calc 裡
    if (negative) {
        px = -px;
    }

    return `calc(${px} / ${DESIGN_WIDTH_UNIT} * 1vw)`;
}



module.exports = {
    content: ["./template/**/*.{php,html,js,ts,vue}"],
    theme: {
        screens: {
            xl: { min: "1201px", max: "1700px" },
            lg: { max: "1200px" },
            md: { max: "900px" },
            sm: { max: "600px" },
            desktop: { min: "1200px" },
        },
        fontFamily: {
            'sans': ['Noto Sans TC', 'sans-serif'],
            'serif': ['Noto Serif TC', 'serif'],
            // 'num': ['EB Garamond', 'serif'],
            // 'en': ["Red Hat Display", 'sans-serif'],
        },
        container: {
            center: true,
            screens: false,
        },
        extend: {
            colors: {
                gray: {
                    400: '#727171',
                },
                blue: {
                    400: '#0068b7',
                },
                yellow: {
                    400: '#fad91f',
                },
            },
            fontSize: {
                'xs': ['12px'],
                'sm': ['14px'],
                'base': ['16px'],
                'lg': ['18px'],
                'xl': ['20px'],
                '2xl': ['24px'], //use
                '3xl': ['40px'],
                '4xl': ['66px'],
                '5xl': ['86px'],
                '6xl': ['108px'],
            },
            letterSpacing: {
                'tighter': '-2px',
                'tight': '-1px',
                'none': '0px',
                'normal': '1px',
                'wide': '3px',
                'wider': '6px',
                'widest': '9px',
            },
            lineHeight: {
                '1.1': '1.1',
                '1.2': '1.2',
                '1.3': '1.3',
                '1.4': '1.4',
                '1.5': '1.5',
                '1.6': '1.6',
                '1.8': '1.8',
                '2': '2',
            },
            borderRadius: {
                sm: '7px',
                md: '14px',
                lg: '21px',
            },
            zIndex: {
                '60': '60',
                '70': '70',
                '80': '80',
                '90': '90',
            },
            transitionDelay: {
                '0': '0s',
            },
            animation: {
                'spin-slow': 'spin 10s linear infinite',
            },
            width: {
                'fill': '-webkit-fill-available',
            },
            maxWidth: {
                'fill': '-webkit-fill-available',
            },
            height: {
                'fill': '-webkit-fill-available',
            },
            maxHeight: {
                'fill': '-webkit-fill-available',
            },
        },
    },
    variants: {
        extend: {},
    },
    plugins: [
        plugin(function ({ matchUtilities, config }) {
            /* ============================================================
            #1: text-fluid (支援 text-fluid-lg / text-fluid-[16])
            ============================================================ */
            const allFontSizes = config("theme.fontSize")

            const sizeMap = Object.fromEntries(
                Object.entries(allFontSizes).map(([key, val]) => {
                    const px = parseFloat(val[0])
                    return [key, px]
                })
            )

            matchUtilities({
                "vw-text": (value) => {
                    const n = parseFloat(value)
                        return {
                            fontSize: fluidFont(n),
                        }
                    },
                }, {
                values: {
                    ...sizeMap, // ✔ 支援 text-fluid-lg
                    ...{},      // ✔ 保留 arbitrary text-fluid-[16]
                },
            })

            /* ============================================================
            #2: vw spacing utilities (vw-top / vw-mt / vw-pl ... )
            ============================================================ */
            const spacingScale = config("theme.spacing")
            const vwProperties = {
                "vw-top": "top",
                "vw-right": "right",
                "vw-bottom": "bottom",
                "vw-left": "left",

                "vw-m": "margin",
                "vw-mt": "marginTop",
                "vw-mr": "marginRight",
                "vw-mb": "marginBottom",
                "vw-ml": "marginLeft",
                "vw-mx": ["marginLeft", "marginRight"],
                "vw-my": ["marginTop", "marginBottom"],

                "vw-p": "padding",
                "vw-pt": "paddingTop",
                "vw-pr": "paddingRight",
                "vw-pb": "paddingBottom",
                "vw-pl": "paddingLeft",
                "vw-px": ["paddingLeft", "paddingRight"],
                "vw-py": ["paddingTop", "paddingBottom"],

                "vw-w": "width",
                "vw-h": "height",
                "vw-max-w": "maxWidth",
                "vw-max-h": "maxHeight",
                "vw-min-w": "minWidth",
                "vw-min-h": "minHeight",

                "vw-gap": "gap",
                "vw-gap-x": "columnGap",
                "vw-gap-y": "rowGap",

                "vw-space-x": "space-x",
                "vw-space-y": "space-y",

                "vw-translate-x": "--tw-translate-x",
                "vw-translate-y": "--tw-translate-y",

                "vw-basis": "flex-basis",
            };
            const allowNegative = new Set([
                "vw-top",
                "vw-right",
                "vw-bottom",
                "vw-left",

                "vw-m",
                "vw-mt",
                "vw-mr",
                "vw-mb",
                "vw-ml",
                "vw-mx",
                "vw-my",

                "vw-space-x",
                "vw-space-y",

                "vw-translate-x",
                "vw-translate-y",
            ]);

            matchUtilities(
                Object.fromEntries(
                    Object.entries(vwProperties).map(([key, cssProp]) => [
                        key,
                        (value) => {
                            const isNegative =
                            typeof value === "string" && value.startsWith("-");

                            if (isNegative && !allowNegative.has(key)) {
                                return {}; // 非允許 class，不輸出 CSS
                            }

                            const cssValue = toVW(value);

                            // space-x
                            if (cssProp === "space-x") {
                                return {
                                    "& > * + *": {
                                        marginLeft: cssValue,
                                    },
                                };
                            }

                            // space-y
                            if (cssProp === "space-y") {
                                return {
                                    "& > * + *": {
                                        marginTop: cssValue,
                                    },
                                };
                            }

                            // translate-x
                            if (cssProp === "--tw-translate-x") {
                                return {
                                    "--tw-translate-x": cssValue,
                                    transform:
                                    "translate(var(--tw-translate-x), var(--tw-translate-y))",
                                };
                            }

                            // translate-y
                            if (cssProp === "--tw-translate-y") {
                                return {
                                    "--tw-translate-y": cssValue,
                                    transform:
                                    "translate(var(--tw-translate-x), var(--tw-translate-y))",
                                };
                            }

                            if (Array.isArray(cssProp)) {
                                const obj = {};
                                cssProp.forEach((prop) => {
                                    obj[prop] = cssValue;
                                });
                                return obj;
                            }

                            return { [cssProp]: cssValue };
                        },
                    ])
                ), {
                    values: spacingScale,
                    supportsArbitraryValues: true,
                    supportsNegativeValues: true,
                }
            );
        }),
    ],
};