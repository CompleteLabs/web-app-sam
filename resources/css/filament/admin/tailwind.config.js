import preset from '../../../../vendor/filament/filament/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                // shadcn/ui color system integrated with Filament
                border: "hsl(var(--border))",
                input: "hsl(var(--input))",
                ring: "hsl(var(--ring))",
                background: "hsl(var(--background))",
                foreground: "hsl(var(--foreground))",
                primary: {
                    DEFAULT: "hsl(var(--primary))",
                    foreground: "hsl(var(--primary-foreground))",
                },
                secondary: {
                    DEFAULT: "hsl(var(--secondary))",
                    foreground: "hsl(var(--secondary-foreground))",
                },
                destructive: {
                    DEFAULT: "hsl(var(--destructive))",
                    foreground: "hsl(var(--destructive-foreground))",
                },
                muted: {
                    DEFAULT: "hsl(var(--muted))",
                    foreground: "hsl(var(--muted-foreground))",
                },
                accent: {
                    DEFAULT: "hsl(var(--accent))",
                    foreground: "hsl(var(--accent-foreground))",
                },
                popover: {
                    DEFAULT: "hsl(var(--popover))",
                    foreground: "hsl(var(--popover-foreground))",
                },
                card: {
                    DEFAULT: "hsl(var(--card))",
                    foreground: "hsl(var(--card-foreground))",
                },
                chart: {
                    "1": "hsl(var(--chart-1))",
                    "2": "hsl(var(--chart-2))",
                    "3": "hsl(var(--chart-3))",
                    "4": "hsl(var(--chart-4))",
                    "5": "hsl(var(--chart-5))"
                }
            },
            borderRadius: {
                lg: "var(--radius)",
                md: "calc(var(--radius) - 2px)",
                sm: "calc(var(--radius) - 4px)",
            },
            keyframes: {
                "accordion-down": {
                    from: { height: "0" },
                    to: { height: "var(--radix-accordion-content-height)" },
                },
                "accordion-up": {
                    from: { height: "var(--radix-accordion-content-height)" },
                    to: { height: "0" },
                },
                "fade-in": {
                    from: { opacity: "0" },
                    to: { opacity: "1" },
                },
                "fade-out": {
                    from: { opacity: "1" },
                    to: { opacity: "0" },
                },
                "zoom-in": {
                    from: { transform: "scale(0.95)" },
                    to: { transform: "scale(1)" },
                },
                "zoom-out": {
                    from: { transform: "scale(1)" },
                    to: { transform: "scale(0.95)" },
                },
                "slide-in-from-top": {
                    from: { transform: "translateY(-2px)" },
                    to: { transform: "translateY(0)" },
                },
                "slide-in-from-bottom": {
                    from: { transform: "translateY(2px)" },
                    to: { transform: "translateY(0)" },
                },
                "slide-in-from-left": {
                    from: { transform: "translateX(-2px)" },
                    to: { transform: "translateX(0)" },
                },
                "slide-in-from-right": {
                    from: { transform: "translateX(2px)" },
                    to: { transform: "translateX(0)" },
                },
                "slide-out-to-top": {
                    from: { transform: "translateY(0)" },
                    to: { transform: "translateY(-2px)" },
                },
                "slide-out-to-bottom": {
                    from: { transform: "translateY(0)" },
                    to: { transform: "translateY(2px)" },
                },
                "slide-out-to-left": {
                    from: { transform: "translateX(0)" },
                    to: { transform: "translateX(-2px)" },
                },
                "slide-out-to-right": {
                    from: { transform: "translateX(0)" },
                    to: { transform: "translateX(2px)" },
                },
            },
            animation: {
                "accordion-down": "accordion-down 0.2s ease-out",
                "accordion-up": "accordion-up 0.2s ease-out",
                "fade-in": "fade-in 0.2s ease-out",
                "fade-out": "fade-out 0.2s ease-out",
                "zoom-in": "zoom-in 0.2s ease-out",
                "zoom-out": "zoom-out 0.2s ease-out",
                "slide-in-from-top": "slide-in-from-top 0.2s ease-out",
                "slide-in-from-bottom": "slide-in-from-bottom 0.2s ease-out",
                "slide-in-from-left": "slide-in-from-left 0.2s ease-out",
                "slide-in-from-right": "slide-in-from-right 0.2s ease-out",
                "slide-out-to-top": "slide-out-to-top 0.2s ease-out",
                "slide-out-to-bottom": "slide-out-to-bottom 0.2s ease-out",
                "slide-out-to-left": "slide-out-to-left 0.2s ease-out",
                "slide-out-to-right": "slide-out-to-right 0.2s ease-out",
            },
            fontFamily: {
                sans: [
                    "Inter",
                    "ui-sans-serif",
                    "system-ui",
                    "-apple-system",
                    "BlinkMacSystemFont",
                    "Segoe UI",
                    "Roboto",
                    "Helvetica Neue",
                    "Arial",
                    "Noto Sans",
                    "sans-serif",
                    "Apple Color Emoji",
                    "Segoe UI Emoji",
                    "Segoe UI Symbol",
                    "Noto Color Emoji",
                ],
            },
        },
    },
    plugins: [
        require('tailwind-scrollbar'),
    ],
}
