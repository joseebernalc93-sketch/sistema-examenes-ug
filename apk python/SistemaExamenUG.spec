# -*- mode: python ; coding: utf-8 -*-
import os

block_cipher = None

a = Analysis(
    ['examen_seguro.py'],
    pathex=[],
    binaries=[],
    datas=[
        ('Imagenes/LogoUGcolor.png', 'Imagenes'),
        ('Imagenes/LogoUGcolor.ico', 'Imagenes')
    ],
    hiddenimports=[
        'screeninfo',
        'wmi',
        'keyboard',
        'psutil',
        'PIL',
        'PIL.Image',
        'PIL.ImageTk',
        'selenium',
        'selenium.webdriver',
        'selenium.webdriver.common.by',
        'undetected_chromedriver',
        'win32gui',
        'win32con',
        'win32process',
        'win32api'
    ],
    hookspath=[],
    hooksconfig={},
    runtime_hooks=[],
    excludes=[],
    noarchive=False,
    optimize=0,
)
pyz = PYZ(a.pure, a.zipped_data, cipher=block_cipher)

exe = EXE(
    pyz,
    a.scripts,
    a.binaries,
    a.datas,
    [],
    name='SistemaExamenUG',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=False,
    disable_windowed_traceback=False,
    argv_emulation=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
    icon='Imagenes/LogoUGcolor.ico',
)
