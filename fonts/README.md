# Fonts Directory

## Required Font File

Please place your `roman.ttf` font file in this directory.

### File Structure:
```
fonts/
├── README.md
└── roman.ttf  ← Place your font file here
```

### Usage:
The system will automatically use the Roman font if `roman.ttf` is present in this directory. If the font file is not found, it will fallback to:
1. Cormorant Garamond (Google Fonts)
2. Playfair Display (Google Fonts) 
3. Georgia (system font)
4. Times New Roman (system font)
5. Generic serif

### Font Format:
- **File name**: `roman.ttf` (exactly)
- **Format**: TrueType Font (.ttf)
- **Weight**: Normal
- **Style**: Normal

### Current Status:
✅ Roman.ttf - **FOUND** (Roman font should be active)

### Font Loading:
The Roman font is now loaded and should be visible on the page. Check the browser console (F12) for font loading confirmation messages.