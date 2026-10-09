import { defineConfig } from 'vite';
import pollora from '@pollora/vite-config';

export default defineConfig({
    plugins: [
        pollora({
            type: 'theme',
            themeJson: {
                // Inter needs its @font-face, which only theme.json can declare
                disableTailwindFonts: true,
                fontSizeLabels: {
                    xs: 'Extra Small',
                    sm: 'Small',
                    base: 'Medium',
                    lg: 'Large',
                    xl: 'Extra Large',
                    '2xl': '2X Large',
                    '3xl': '3X Large',
                    '4xl': '4X Large',
                    '5xl': '5X Large',
                    '6xl': '6X Large',
                    '7xl': '7X Large',
                },
                borderRadiusLabels: {
                    sm: 'Small',
                    md: 'Medium',
                    lg: 'Large',
                    xl: 'Extra Large',
                    '2xl': '2X Large',
                },
            },
        }),
    ],
});
