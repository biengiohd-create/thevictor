import {sheetColorPlugin} from './color-policy.js';
import {dailySheetNamePlugin} from './sheet-name-policy.js';
import {defineConfig} from 'vite';export default defineConfig({base:'./',plugins:[dailySheetNamePlugin(),sheetColorPlugin()],build:{assetsInlineLimit:0,rollupOptions:{output:{entryFileNames:'assets/editor.js',assetFileNames:'assets/[name][extname]'}}}});