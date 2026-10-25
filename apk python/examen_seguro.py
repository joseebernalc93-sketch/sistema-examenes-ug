#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
VERSIÓN 9.7.1 - Sistema de Exámenes UG
Cambios respecto a v9.7:
- CORREGIDO: Cursor del mouse ya no aparece en estado de "cargando" durante el examen
- OPTIMIZADO: Procesos de seguridad (watchdogs) ahora usan DETACHED_PROCESS
- MEJORADO: Timeouts más cortos (0.5s) en comandos de seguridad para mejor rendimiento

Cambios respecto a v9.6:
- NUEVO: Las preguntas con IMAGEN valen más que las sin imagen
- NUEVO: Distribución inteligente de puntajes que siempre suma 10.00
- NUEVO: Indicador visual (⭐) para preguntas con imagen (mayor valor)
- NUEVO: Colores diferenciados: dorado para preguntas con imagen, azul para normales
- CORREGIDO: La distribución usa ID de pregunta como clave (no índice)

Ejemplo con 15 preguntas (2 con imagen):
- 2 preguntas con imagen × 1.10 pts = 2.20 pts (⭐ dorado)
- 13 preguntas sin imagen × 0.60 pts = 7.80 pts (azul)
- Total = 10.00 pts exactos

La distribución se guarda como diccionario {pregunta_id: puntaje} para
evitar desalineación cuando se mezclan las preguntas.

Cambios de v9.6:
- MEJORADO: Estética tipo Moodle en toda la aplicación
- AGREGADO: Badge de Secuencial/Libre en la lista de exámenes
- MEJORADO: Cards de exámenes con mejor diseño
- MEJORADO: Colores y tipografía más profesionales
- MEJORADO: Interfaz del examen con estilo Moodle
- MEJORADO: Imagen de pregunta centrada
- MEJORADO: Scroll condicional (solo cuando contenido es extenso)
- MEJORADO: Solo enunciado en negrita, opciones en fuente normal
- AGREGADO: Click en espacio vacío deselecciona la respuesta
- MEJORADO: Enunciado con ajuste automático de ancho
- ELIMINADO: Botón "Atrás" innecesario en modo libre
- CORREGIDO: Distorsión de texto al volver del resumen (timers duplicados)
- OPTIMIZADO: Gestión de memoria y recolección de basura
"""

import sys
try:
    if hasattr(sys.stdout, 'reconfigure'):
        getattr(sys.stdout, 'reconfigure')(encoding='utf-8', errors='replace')
    if hasattr(sys.stderr, 'reconfigure'):
        getattr(sys.stderr, 'reconfigure')(encoding='utf-8', errors='replace')
except Exception:
    pass

import tkinter as tk
from tkinter import ttk, messagebox
import datetime
import subprocess
import sys
import os
import json
import threading
import time
import traceback
import base64
import io
import re
import ctypes
import platform
import random
import tempfile
import shutil
from ctypes import wintypes

# =======================================================
#  CONSTANTES SUBPROCESS PARA WINDOWS
# =======================================================
# Definir DETACHED_PROCESS si no existe (para evitar cursor de carga)
if not hasattr(subprocess, 'DETACHED_PROCESS'):
    subprocess.DETACHED_PROCESS = 0x00000008

# =======================================================
#  APP INFO (título + AppUserModelID)
# =======================================================
APP_NAME = "Sistema de Exámenes UG"
APP_ID = "UG.ExamenSeguro.V9_7.FullBlocker"

# =======================================================
#  COLORES INSTITUCIONALES UG - PALETA PROFESIONAL
# =======================================================
COLORS = {
    'primary': '#0b4f8a',          # Azul Institucional UG
    'primary_dark': '#07335c',     # Azul Marino Profundo UG
    'primary_light': '#e0f2fe',    # Azul Claro Suave
    'accent': '#1d4ed8',           # Azul Vibrante
    'secondary': '#475569',        # Gris Pizarra Slate 600
    'success': '#059669',          # Verde Esmeralda 600
    'success_dark': '#047857',     # Verde Esmeralda 700
    'success_light': '#ecfdf5',    # Verde Claro Suave
    'danger': '#dc2626',           # Rojo Coral
    'danger_dark': '#b91c1c',
    'danger_light': '#fef2f2',
    'warning': '#d97706',          # Ámbar 600
    'warning_dark': '#b45309',
    'warning_light': '#fffbeb',
    'info': '#0284c7',             # Cyan/Azul Info
    'info_light': '#f0f9ff',
    'bg_light': '#f8fafc',         # Fondo Pizarra Claro Ultra Limpio
    'bg_white': '#ffffff',
    'bg_section': '#f1f5f9',       # Fondo Sección
    'question_bg': '#f8fafc',
    'border': '#e2e8f0',           # Borde Slate Sutil
    'border_dark': '#cbd5e1',
    'text_dark': '#0f172a',        # Texto Casi Negro Slate 900
    'text_muted': '#64748b',       # Texto Secundario Slate 500
    'badge_libre': '#0891b2',      # Cyan
    'badge_secuencial': '#7c3aed', # Púrpura Elegante
    'card_border_active': '#10b981',
    'card_border_pending': '#f59e0b',
    'card_border_closed': '#94a3b8',
    'enunciado_bg': '#f8fafc',
    'enunciado_border': '#cbd5e1',
    'opcion_bg': '#ffffff',
    'opcion_border': '#e2e8f0',
    'opcion_hover': '#f1f5f9',
    'imagen_border': '#334155',
    'zoom_btn_bg': '#0f172a',
    'zoom_btn_fg': '#ffffff',
    'card_bg': '#ffffff',
    'card_shadow': '#e2e8f0',
    'card_header_activo': '#ecfdf5',
    'card_header_cerrado': '#f1f5f9',
    'card_header_pendiente': '#fffbeb',
    'card_header_completado': '#f0fdf4',
    'badge_activo_bg': '#059669',
    'badge_cerrado_bg': '#64748b',
    'badge_pendiente_bg': '#d97706',
    'badge_secuencial_bg': '#7c3aed',
    'badge_libre_bg': '#0891b2',
    'info_icon': '#64748b',
    'divider': '#e2e8f0',
}

# =======================================================
#  LOGGING
# =======================================================
LOG_FILE = "debug_examen.txt"
LOG_TO_FILE = False  # Desactiva escritura a archivo para evitar crear debug_examen.txt

def log(mensaje):
    timestamp = datetime.datetime.now().strftime("%H:%M:%S")
    texto = f"[{timestamp}] {mensaje}"
    try:
        print(texto)
    except UnicodeEncodeError:
        try:
            print(texto.encode('ascii', errors='replace').decode('ascii'))
        except Exception:
            pass
    if LOG_TO_FILE and LOG_FILE:
        try:
            with open(LOG_FILE, "a", encoding="utf-8") as f:
                f.write(texto + "\n")
        except Exception:
            pass

def log_error(e):
    log(f"ERROR: {e}")
    log(traceback.format_exc())

if LOG_TO_FILE and LOG_FILE:
    try:
        open(LOG_FILE, "w").close()
    except:
        pass

log("=== INICIANDO APLICACIÓN V9.6 ===")

# =======================================================
#  HELPERS
# =======================================================
def resource_path(relative_path: str) -> str:
    base_path = getattr(sys, "_MEIPASS", os.path.abspath("."))
    return os.path.join(base_path, relative_path)

def set_windows_app_id(app_id: str):
    if sys.platform == "win32":
        try:
            ctypes.windll.shell32.SetCurrentProcessExplicitAppUserModelID(app_id)
            log(f"✓ AppUserModelID configurado: {app_id}")
        except Exception as e:
            log(f"⚠ No se pudo setear AppUserModelID: {e}")

def is_windows_admin() -> bool:
    """True si el proceso se está ejecutando con privilegios de administrador (Windows)."""
    if sys.platform != "win32":
        return False
    try:
        return bool(ctypes.windll.shell32.IsUserAnAdmin())
    except Exception:
        return False

def normalizar_bool(valor):
    if isinstance(valor, bool):
        return valor
    if valor is None:
        return False
    if isinstance(valor, (int, float)):
        return valor != 0
    if isinstance(valor, str):
        return valor.strip().lower() in ("1", "true", "t", "yes", "y", "si")
    return bool(valor)


def mezclar_lista_deterministica(items, seed):
    """Baraja una lista con semilla estable para mantener orden único por estudiante/examen."""
    rng = random.Random(str(seed))
    barajada = list(items)
    rng.shuffle(barajada)
    return barajada


def _lista_claves_orden(items):
    orden = []
    for item in items:
        if isinstance(item, dict):
            orden.append(str(item.get('id', '')))
        else:
            orden.append(str(item))
    return orden


def mezclar_lista_deterministica_distinta(items, seed):
    """Baraja con semilla estable y evita devolver el mismo orden original."""
    rng = random.Random(str(seed))
    original = list(items)
    barajada = list(items)
    rng.shuffle(barajada)
    if len(barajada) > 1 and _lista_claves_orden(barajada) == _lista_claves_orden(original):
        # Forzar un cambio de orden determinista
        barajada[-1], barajada[-2] = barajada[-2], barajada[-1]
    return barajada

def calcular_distribucion_puntajes(total_preguntas, preguntas_con_imagen=0):
    """
    Calcula la distribución de puntajes para que siempre sumen 10.
    Las preguntas con imagen valen más que las sin imagen.
    Retorna una lista con el puntaje de cada pregunta.
    """
    if total_preguntas <= 0:
        return []
    
    preguntas_sin_imagen = total_preguntas - preguntas_con_imagen
    
    # Si no hay preguntas con imagen, distribución simple
    if preguntas_con_imagen == 0:
        return calcular_distribucion_simple(total_preguntas)
    
    # Valores permitidos (más granulares para mejor ajuste)
    valores = [round(x * 0.05, 2) for x in range(5, 101)]  # 0.25 a 5.00 en pasos de 0.05
    
    mejor_distribucion = None
    mejor_score = float('inf')
    
    # Buscar combinación donde valor_imagen > valor_normal
    for v_normal in valores:
        for v_imagen in valores:
            if v_imagen <= v_normal:
                continue
            
            suma = round(preguntas_sin_imagen * v_normal + preguntas_con_imagen * v_imagen, 2)
            
            if suma == 10.00:
                # Preferir que la imagen valga entre 1.5x y 3x más
                ratio = v_imagen / v_normal if v_normal > 0 else 10
                score = abs(ratio - 2.0) * 10
                
                # Bonus por valores "redondos"
                if v_normal * 100 % 25 == 0:
                    score -= 1
                if v_imagen * 100 % 25 == 0:
                    score -= 1
                
                if score < mejor_score:
                    mejor_score = score
                    mejor_distribucion = {
                        'v_normal': v_normal,
                        'v_imagen': v_imagen,
                        'n_normal': preguntas_sin_imagen,
                        'n_imagen': preguntas_con_imagen
                    }
    
    if mejor_distribucion:
        v_normal = mejor_distribucion['v_normal']
        v_imagen = mejor_distribucion['v_imagen']
        n_normal = mejor_distribucion['n_normal']
        n_imagen = mejor_distribucion['n_imagen']
        
        # La distribución se asignará según el orden de preguntas
        # Las preguntas con imagen recibirán v_imagen, las demás v_normal
        return {
            'tipo': 'con_imagen',
            'valor_imagen': v_imagen,
            'valor_normal': v_normal,
            'cant_imagen': n_imagen,
            'cant_normal': n_normal
        }
    
    # Fallback: calcular valores exactos
    divisor = preguntas_sin_imagen + (2 * preguntas_con_imagen)
    v_normal = round(10.0 / divisor, 2)
    v_imagen = round(v_normal * 2, 2)
    
    return {
        'tipo': 'calculado',
        'valor_imagen': v_imagen,
        'valor_normal': v_normal,
        'cant_imagen': preguntas_con_imagen,
        'cant_normal': preguntas_sin_imagen
    }

def calcular_distribucion_simple(total_preguntas):
    """
    Distribución simple cuando no hay preguntas con imagen.
    """
    if total_preguntas <= 0:
        return []
    
    # Casos especiales donde todas valen igual
    casos_uniformes = {
        1: 10.00, 2: 5.00, 4: 2.50, 5: 2.00, 
        8: 1.25, 10: 1.00, 20: 0.50, 40: 0.25
    }
    
    if total_preguntas in casos_uniformes:
        valor = casos_uniformes[total_preguntas]
        return [valor] * total_preguntas
    
    # Valores "bonitos" permitidos
    valores_bonitos = [0.25, 0.50, 0.75, 1.00, 1.25, 1.50, 1.75, 2.00, 2.50, 3.00, 3.50, 4.00, 5.00]
    
    mejor_distribucion = None
    mejor_score = float('inf')
    
    for i, v1 in enumerate(valores_bonitos):
        for v2 in valores_bonitos[i:]:
            for n1 in range(total_preguntas + 1):
                n2 = total_preguntas - n1
                suma = round(n1 * v1 + n2 * v2, 2)
                
                if suma == 10.00:
                    score = abs(n1 - n2) + abs(v2 - v1) * 10
                    
                    if score < mejor_score:
                        mejor_score = score
                        mejor_distribucion = {'n1': n1, 'v1': v1, 'n2': n2, 'v2': v2}
    
    if mejor_distribucion:
        n1 = mejor_distribucion['n1']
        v1 = mejor_distribucion['v1']
        n2 = mejor_distribucion['n2']
        v2 = mejor_distribucion['v2']
        
        distribucion = [v1] * n1 + [v2] * n2
        random.shuffle(distribucion)
        return distribucion
    
    # Fallback
    valor_base = round(10.0 / total_preguntas, 2)
    distribucion = [valor_base] * total_preguntas
    
    suma_actual = sum(distribucion)
    diferencia = round(10.00 - suma_actual, 2)
    
    if diferencia != 0:
        ajuste = 0.01 if diferencia > 0 else -0.01
        for i in range(min(int(abs(diferencia / 0.01)), total_preguntas)):
            distribucion[i] = round(distribucion[i] + ajuste, 2)
    
    return distribucion

# =======================================================
#  IMPORTACIONES
# =======================================================
uc = None
By = None
keyboard = None
get_monitors = None
wmi = None
Image = None
ImageTk = None
psutil = None

SELENIUM_AVAILABLE = False
try:
    import undetected_chromedriver as uc
    from selenium.webdriver.common.by import By
    SELENIUM_AVAILABLE = True
    log("✓ Selenium disponible")
except ImportError as e:
    log(f"✗ Selenium no disponible: {e}")

KEYBOARD_AVAILABLE = False
try:
    import keyboard
    KEYBOARD_AVAILABLE = True
    log("✓ Keyboard disponible")
except:
    log("✗ Keyboard no disponible")

SCREENINFO_AVAILABLE = False
try:
    from screeninfo import get_monitors
    SCREENINFO_AVAILABLE = True
    log("✓ Screeninfo disponible")
except:
    log("✗ Screeninfo no disponible")

WMI_AVAILABLE = False
try:
    if sys.platform == 'win32':
        import wmi
        WMI_AVAILABLE = True
        log("✓ WMI disponible")
except:
    log("✗ WMI no disponible")

PIL_AVAILABLE = False
try:
    from PIL import Image, ImageTk
    PIL_AVAILABLE = True
    log("✓ PIL disponible")
except:
    log("✗ PIL no disponible - las imágenes no se mostrarán")

PSUTIL_AVAILABLE = False
try:
    import psutil
    PSUTIL_AVAILABLE = True
    log("✓ psutil disponible")
except:
    log("✗ psutil no disponible - no se podrán suspender navegadores")

# =======================================================
#  CONFIGURACIÓN
# =======================================================
DEFAULT_API_URL = "https://sistema-examenes-ug.vercel.app/api_examen.php"
API_BASE_URL = os.environ.get("EXAMEN_API_URL", DEFAULT_API_URL)

# Cargar URL alternativa desde config.json si existe
_cfg_file = resource_path("config.json")
if not os.path.exists(_cfg_file):
    _cfg_file = os.path.join(os.path.dirname(os.path.abspath(__file__)), "config.json")
if os.path.exists(_cfg_file):
    try:
        with open(_cfg_file, "r", encoding="utf-8") as _f_cfg:
            _loaded_cfg = json.load(_f_cfg)
            if "api_url" in _loaded_cfg and _loaded_cfg["api_url"]:
                API_BASE_URL = _loaded_cfg["api_url"]
                log(f"URL de API cargada desde config.json: {API_BASE_URL}")
    except Exception as _e_cfg:
        log(f"No se pudo leer config.json: {_e_cfg}")
MENSAJES_POLL_MS = 4000
MENSAJES_MAX = 200

# =======================================================
#  VARIABLES GLOBALES
# =======================================================
examen_activo = False
usuario_actual = None
tiempo_restante_global = 0
respuestas_guardadas = {}
orden_preguntas_ids = []
examen_actual = None
preguntas_cargadas = []
modo_secuencial = False
pregunta_maxima_alcanzada = 0
puntaje_por_pregunta = 1.0  # Puntaje calculado automáticamente (10/N)
distribucion_puntajes = {}  # Diccionario {pregunta_id: puntaje} - las preguntas con imagen valen más

# Variable global para monitoreo permanente
monitoreo_pantallas_activo = True

# =======================================================
#  SEGURIDAD: DETECCIÓN DE VM
# =======================================================
VM_KEYWORDS = [
    "virtualbox", "vbox", "vmware", "kvm", "qemu", "xen",
    "parallels", "hyper-v", "hyperv", "virtual machine", "virtual pc"
]
VM_PROCESOS = [
    "vboxservice.exe", "vboxtray.exe", "vmtoolsd.exe", "vmwaretray.exe",
    "vmwareuser.exe", "vmms.exe", "vmmem", "qemu-ga.exe", "prl_tools.exe",
    "xenservice.exe", "xensvc.exe"
]

def _match_vm_keyword(texto):
    t = (texto or "").lower()
    return any(k in t for k in VM_KEYWORDS)

def detectar_vm():
    if sys.platform != 'win32':
        return False, ""
    try:
        if WMI_AVAILABLE:
            try:
                w = wmi.WMI()
                cs_list = w.Win32_ComputerSystem()
                if cs_list:
                    cs = cs_list[0]
                    campos = [cs.Manufacturer, cs.Model, cs.SystemFamily]
                    for campo in campos:
                        if _match_vm_keyword(campo):
                            return True, f"Equipo virtual detectado: {campo}"
                bios_list = w.Win32_BIOS()
                if bios_list:
                    bios = bios_list[0]
                    campos = [bios.Manufacturer, bios.SerialNumber, bios.Version]
                    for campo in campos:
                        if _match_vm_keyword(campo):
                            return True, f"BIOS virtual detectada: {campo}"
            except Exception as e:
                log(f"⚠ WMI VM check error: {e}")

        if PSUTIL_AVAILABLE:
            for proc in psutil.process_iter(['name']):
                try:
                    nombre = (proc.info.get('name') or "").lower()
                    if nombre in [p.lower() for p in VM_PROCESOS]:
                        return True, f"Proceso de VM activo: {nombre}"
                except Exception:
                    continue

        uname_str = " ".join(platform.uname())
        if _match_vm_keyword(uname_str):
            return True, f"Cadena de sistema indica VM: {uname_str}"
    except Exception as e:
        log(f"⚠ VM detect error: {e}")
    return False, ""

# =======================================================
#  SEGURIDAD: DETECCIÓN DE PANTALLAS (Windows)
# =======================================================
SM_CMONITORS = 80
DISPLAY_DEVICE_ATTACHED_TO_DESKTOP = 0x1
DISPLAY_DEVICE_MIRRORING_DRIVER = 0x8
DISPLAY_DEVICE_ACTIVE = 0x1

class DISPLAY_DEVICEW(ctypes.Structure):
    _fields_ = [
        ("cb", wintypes.DWORD),
        ("DeviceName", wintypes.WCHAR * 32),
        ("DeviceString", wintypes.WCHAR * 128),
        ("StateFlags", wintypes.DWORD),
        ("DeviceID", wintypes.WCHAR * 128),
        ("DeviceKey", wintypes.WCHAR * 128),
    ]

def _contar_monitores_getsystemmetrics():
    """Cuenta monitores lógicos activos."""
    try:
        return int(ctypes.windll.user32.GetSystemMetrics(SM_CMONITORS))
    except:
        return 1

def _contar_monitores_enumdisplaydevices():
    """Cuenta monitores lógicos conectados al escritorio."""
    try:
        user32 = ctypes.windll.user32
        EnumDisplayDevicesW = user32.EnumDisplayDevicesW
        EnumDisplayDevicesW.argtypes = [wintypes.LPCWSTR, wintypes.DWORD, ctypes.POINTER(DISPLAY_DEVICEW), wintypes.DWORD]
        EnumDisplayDevicesW.restype = wintypes.BOOL

        mon_ids = set()
        i = 0
        while True:
            dd = DISPLAY_DEVICEW()
            dd.cb = ctypes.sizeof(dd)
            if not EnumDisplayDevicesW(None, i, ctypes.byref(dd), 0):
                break
            i += 1

            if dd.StateFlags & DISPLAY_DEVICE_MIRRORING_DRIVER:
                continue

            j = 0
            while True:
                dm = DISPLAY_DEVICEW()
                dm.cb = ctypes.sizeof(dm)
                if not EnumDisplayDevicesW(dd.DeviceName, j, ctypes.byref(dm), 0):
                    break
                j += 1

                if dm.StateFlags & DISPLAY_DEVICE_MIRRORING_DRIVER:
                    continue

                if dm.StateFlags & DISPLAY_DEVICE_ATTACHED_TO_DESKTOP:
                    key = (dm.DeviceID or "").strip() or (dm.DeviceName or "").strip() or (dm.DeviceString or "").strip()
                    if key:
                        mon_ids.add(key)

        return max(1, len(mon_ids))
    except Exception as e:
        log(f"EnumDisplayDevices fallo: {e}")
        return 1

def _contar_adaptadores_video_activos():
    """
    DESHABILITADO: Causaba falsos positivos en laptops con GPU híbrida.
    Las laptops con Intel + NVIDIA/AMD tienen múltiples adaptadores
    pero generalmente solo UN monitor físico (la pantalla del laptop).
    """
    # Retornamos 1 para no interferir con la detección real
    return 1

def _contar_displays_fisicos_conectados():
    """
    Metodo para detectar displays fisicos conectados.
    Incluye monitores no activos para bloquear conexiones externas.
    """
    if sys.platform != 'win32':
        return 1
    
    try:
        user32 = ctypes.windll.user32
        EnumDisplayDevicesW = user32.EnumDisplayDevicesW
        EnumDisplayDevicesW.argtypes = [wintypes.LPCWSTR, wintypes.DWORD, ctypes.POINTER(DISPLAY_DEVICEW), wintypes.DWORD]
        EnumDisplayDevicesW.restype = wintypes.BOOL
        
        monitores_unicos = set()
        
        i = 0
        while True:
            adapter = DISPLAY_DEVICEW()
            adapter.cb = ctypes.sizeof(adapter)
            
            if not EnumDisplayDevicesW(None, i, ctypes.byref(adapter), 0):
                break
            i += 1
            
            # Ignorar drivers de espejo
            if adapter.StateFlags & DISPLAY_DEVICE_MIRRORING_DRIVER:
                continue
            
            # Contar adaptadores aunque no esten activos para detectar conexiones
            
            adapter_name = adapter.DeviceName
            
            # Buscar monitores conectados a este adaptador
            j = 0
            while True:
                monitor = DISPLAY_DEVICEW()
                monitor.cb = ctypes.sizeof(monitor)
                
                # Flag 1 = EDD_GET_DEVICE_INTERFACE_NAME para obtener info real
                if not EnumDisplayDevicesW(adapter_name, j, ctypes.byref(monitor), 1):
                    break
                j += 1
                
                device_id = (monitor.DeviceID or "").strip().upper()
                device_string = (monitor.DeviceString or "").strip()
                
                # Filtrar monitores genéricos/virtuales que no son reales
                if not device_id:
                    continue
                    
                # Ignorar dispositivos virtuales comunes en GPU híbridas
                if "ROOT" in device_id:  # Dispositivo raíz virtual
                    continue
                if "YOURPHONE" in device_id:  # Tu teléfono de Windows
                    continue
                if "VIRTUAL" in device_id.upper():
                    continue
                    
                # Solo contar si tiene un ID de monitor real (contiene MONITOR o DISPLAY)
                if "MONITOR" in device_id or "DISPLAY" in device_id:
                    # Crear una clave única basada en el ID del dispositivo
                    # para evitar contar el mismo monitor dos veces
                    monitores_unicos.add(device_id)
                    log(f"   Monitor detectado: {device_id} ({device_string})")
        
        total = len(monitores_unicos)
        log(f"   Total monitores físicos únicos: {total}")
        return max(1, total)
        
    except Exception as e:
        log(f"Error detectando displays físicos: {e}")
        return 1

def contar_monitores():
    """
    Cuenta displays de forma robusta usando múltiples métodos.
    PRIORIZA GetSystemMetrics que es el más confiable para detectar
    monitores lógicos activos (lo que el usuario realmente ve).
    """
    if sys.platform != 'win32':
        return 1
    
    # Método principal: GetSystemMetrics - cuenta monitores LÓGICOS activos
    # Este es el más confiable porque cuenta lo que Windows muestra al usuario
    a = _contar_monitores_getsystemmetrics()
    log(f"   GetSystemMetrics: {a} monitor(es)")
    
    # Método secundario: EnumDisplayDevices - cuenta configuraciones de display
    b = _contar_monitores_enumdisplaydevices()
    log(f"   EnumDisplayDevices: {b} monitor(es)")
    
    # El metodo c esta deshabilitado para evitar falsos positivos en laptops con GPU hibrida.
    c = 1  # _contar_adaptadores_video_activos() - DESHABILITADO
    d = _contar_displays_fisicos_conectados()
    log(f"   DisplaysFisicos: {d} monitor(es)")
    
    # Si GetSystemMetrics reporta 1, revisar otras fuentes para detectar conexiones.
    if a == 1:
        if d > 1:
            n = d
            log(f"   Multiples detectados por displays fisicos conectados: {n}")
        elif b > 1:
            n = b
            log(f"   Multiples detectados por EnumDisplayDevices: {n}")
        else:
            n = 1
    else:
        n = a
    
    log(f"   >>> RESULTADO FINAL: {n} monitor(es)")
    return max(1, n)

def hay_multiples_pantallas():
    """
    Detecta si hay más de una pantalla/dispositivo de salida conectado.
    Retorna: (hay_multiples: bool, num_displays: int)
    
    MEJORADO: Detecta monitores conectados aunque no esten activos.
    """
    if sys.platform != 'win32':
        return False, 1
    
    # Verificación rápida con GetSystemMetrics primero
    try:
        count_rapido = ctypes.windll.user32.GetSystemMetrics(SM_CMONITORS)
        if count_rapido > 1:
            log(f"   Verificacion rapida: {count_rapido} monitor(es) (GetSystemMetrics)")
            return True, count_rapido
        if count_rapido == 1:
            log("   Verificacion rapida: 1 monitor (GetSystemMetrics), revisando conexiones...")
    except:
        pass
    
    # Verificar conexiones con conteo completo
    num_displays = contar_monitores()
    return num_displays > 1, num_displays

def detectar_solo_segunda_pantalla():
    """
    Detecta si el usuario está en modo 'Solo segunda pantalla'.
    En este modo, la pantalla principal está desactivada y solo se usa la secundaria.
    Retorna: True si está en ese modo.
    """
    if sys.platform != 'win32':
        return False
    
    try:
        user32 = ctypes.windll.user32
        ancho = user32.GetSystemMetrics(0)
        alto = user32.GetSystemMetrics(1)
        
        if ancho == 0 or alto == 0:
            return True
        
        monitores_activos = []
        
        def callback(hMonitor, hdcMonitor, lprcMonitor, dwData):
            monitores_activos.append(hMonitor)
            return True
        
        MONITORENUMPROC = ctypes.WINFUNCTYPE(ctypes.c_bool, ctypes.c_void_p, ctypes.c_void_p, ctypes.POINTER(wintypes.RECT), wintypes.LPARAM)
        callback_func = MONITORENUMPROC(callback)
        user32.EnumDisplayMonitors(None, None, callback_func, 0)
        
        if len(monitores_activos) == 0:
            return True
            
    except Exception as e:
        log(f"Error detectando modo solo segunda pantalla: {e}")
    
    return False

# Control de frecuencia para evitar bucles de DisplaySwitch (especialmente en Windows 10).
DISPLAYSWITCH_COOLDOWN_SEC = 8.0
_last_displayswitch_ts = 0.0

def forzar_pantalla_principal(force=False, motivo=""):
    """
    Fuerza el modo 'Solo pantalla de PC' usando DisplaySwitch.exe
    """
    global _last_displayswitch_ts

    if sys.platform != 'win32':
        return False

    # Evita invocar DisplaySwitch continuamente (en Win10 puede hacer "parpadear"
    # o reaparecer el panel de proyección si se ejecuta en bucle).
    ahora = time.time()
    if not force and (ahora - _last_displayswitch_ts) < DISPLAYSWITCH_COOLDOWN_SEC:
        return False

    try:
        flags = 0
        if hasattr(subprocess, 'CREATE_NO_WINDOW'):
            flags = subprocess.CREATE_NO_WINDOW
        if hasattr(subprocess, 'DETACHED_PROCESS'):
            flags |= subprocess.DETACHED_PROCESS

        subprocess.run(
            ["DisplaySwitch.exe", "/internal"],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            creationflags=flags,
            timeout=1
        )
        _last_displayswitch_ts = ahora
        if motivo:
            log(f">>> Forzado modo 'Solo pantalla de PC' ({motivo})")
        else:
            log(">>> Forzado modo 'Solo pantalla de PC'")
        return True
    except Exception as e:
        log(f"Error forzando pantalla principal: {e}")
        return False

# =======================================================
#  DISPLAY: MOVER A PANTALLA PRINCIPAL
# =======================================================
def mover_a_pantalla_principal(window):
    if not window:
        return
    try:
        window.update_idletasks()
        if sys.platform == 'win32':
            user32 = ctypes.windll.user32
            ancho = user32.GetSystemMetrics(0)
            alto = user32.GetSystemMetrics(1)
        else:
            ancho = window.winfo_screenwidth()
            alto = window.winfo_screenheight()
        window.geometry(f"{ancho}x{alto}+0+0")
    except Exception as e:
        log(f"posicionar pantalla principal error: {e}")

# =======================================================
#  LOW-LEVEL KEYBOARD HOOK
# =======================================================
_keyboard_hook_handle = None
_keyboard_proc_ref = None

if sys.platform == 'win32':
    user32 = ctypes.windll.user32
    kernel32 = ctypes.windll.kernel32

    WM_KEYDOWN = 0x100
    WM_SYSKEYDOWN = 0x104
    WM_KEYUP = 0x101
    WM_SYSKEYUP = 0x105
    VK_LWIN = 0x5B
    VK_RWIN = 0x5C
    VK_TAB = 0x09
    VK_P = 0x50
    VK_HOME = 0x24
    VK_SNAPSHOT = 0x2C
    VK_E = 0x45
    VK_D = 0x44
    VK_R = 0x52
    VK_K = 0x4B
    VK_S = 0x53

    LRESULT = getattr(wintypes, 'LRESULT', ctypes.c_ssize_t)

    LowLevelKeyboardProc = ctypes.WINFUNCTYPE(
        LRESULT,
        ctypes.c_int,
        wintypes.WPARAM,
        wintypes.LPARAM
    )

    class KBDLLHOOKSTRUCT(ctypes.Structure):
        _fields_ = [
            ("vkCode", wintypes.DWORD),
            ("scanCode", wintypes.DWORD),
            ("flags", wintypes.DWORD),
            ("time", wintypes.DWORD),
            ("dwExtraInfo", ctypes.POINTER(ctypes.c_ulong))
        ]

    def _low_level_keyboard_proc(nCode, wParam, lParam):
        try:
            if nCode == 0:
                kb = ctypes.cast(lParam, ctypes.POINTER(KBDLLHOOKSTRUCT)).contents
                vk = kb.vkCode
                win_down = (user32.GetAsyncKeyState(VK_LWIN) & 0x8000) or (user32.GetAsyncKeyState(VK_RWIN) & 0x8000)
                alt_down = user32.GetAsyncKeyState(0x12) & 0x8000
                ctrl_down = user32.GetAsyncKeyState(0x11) & 0x8000

                if wParam in (WM_KEYDOWN, WM_SYSKEYDOWN, WM_KEYUP, WM_SYSKEYUP):
                    if vk in (VK_LWIN, VK_RWIN, VK_SNAPSHOT, VK_HOME):
                        return 1
                    if win_down and vk in (VK_P, VK_TAB, VK_E, VK_D, VK_R, VK_K, VK_S):
                        return 1
                    if alt_down and vk in (VK_TAB, VK_SNAPSHOT):
                        return 1
                    if ctrl_down and vk in (VK_TAB, VK_P, VK_SNAPSHOT):
                        return 1
            return user32.CallNextHookEx(_keyboard_hook_handle, nCode, wParam, lParam)
        except Exception as e:
            log(f"hook error: {e}")
            return 0

def instalar_hook_teclado_ll():
    global _keyboard_hook_handle, _keyboard_proc_ref
    if sys.platform != 'win32':
        return
    try:
        _keyboard_proc_ref = LowLevelKeyboardProc(_low_level_keyboard_proc)
        _keyboard_hook_handle = user32.SetWindowsHookExW(13, _keyboard_proc_ref, kernel32.GetModuleHandleW(None), 0)
        if _keyboard_hook_handle:
            log("✓ Hook global de teclado instalado")
        else:
            log("⚠ No se pudo instalar hook global de teclado")
    except Exception as e:
        log(f"⚠ Error instalando hook teclado: {e}")

def desinstalar_hook_teclado_ll():
    if sys.platform != 'win32':
        return
    global _keyboard_hook_handle
    try:
        if _keyboard_hook_handle:
            user32.UnhookWindowsHookEx(_keyboard_hook_handle)
            _keyboard_hook_handle = None
    except Exception as e:
        log(f"Error desinstalando hook: {e}")

# =======================================================
#  TASKBAR (ocultar/mostrar)
# =======================================================
SW_HIDE = 0
SW_SHOW = 5

def ocultar_taskbar():
    if sys.platform != 'win32':
        return
    try:
        user32 = ctypes.windll.user32
        shell_tray = user32.FindWindowW("Shell_TrayWnd", None)
        start_btn = user32.FindWindowW("Button", None)
        for hwnd in (shell_tray, start_btn):
            if hwnd:
                user32.ShowWindow(hwnd, SW_HIDE)
    except Exception as e:
        log(f"Taskbar hide error: {e}")

def mostrar_taskbar():
    if sys.platform != 'win32':
        return
    try:
        user32 = ctypes.windll.user32
        shell_tray = user32.FindWindowW("Shell_TrayWnd", None)
        start_btn = user32.FindWindowW("Button", None)
        for hwnd in (shell_tray, start_btn):
            if hwnd:
                user32.ShowWindow(hwnd, SW_SHOW)
    except Exception as e:
        log(f"Taskbar show error: {e}")

# =======================================================
#  SELENIUM MANAGER
# =======================================================
def detectar_chrome_major_version():
    """Devuelve el major version (ej: 144) de Chrome instalado o None.

    undetected_chromedriver necesita que el ChromeDriver coincida con el major
    de Chrome. Si no se fija, puede descargar el último ChromeDriver y fallar
    con SessionNotCreatedException (mostrándose como "error de conexión").

    Nota: `chrome.exe --version` en Windows no siempre devuelve la versión (a
    veces imprime logs o intenta abrir una sesión existente), por eso aquí se
    lee desde el registro y/o metadatos del ejecutable.
    """
    try:
        # 1) Registro de Windows (forma más confiable)
        if sys.platform == 'win32':
            try:
                import winreg

                reg_paths = (
                    r"Software\Google\Chrome\BLBeacon",
                    r"Software\WOW6432Node\Google\Chrome\BLBeacon",
                )
                for root in (winreg.HKEY_CURRENT_USER, winreg.HKEY_LOCAL_MACHINE):
                    for p in reg_paths:
                        try:
                            with winreg.OpenKey(root, p) as k:
                                v, _ = winreg.QueryValueEx(k, "version")
                            m = re.search(r'(\d+)\.', str(v or ""))
                            if m:
                                return int(m.group(1))
                        except FileNotFoundError:
                            continue
                        except OSError:
                            continue
            except Exception:
                pass

        # 2) Metadatos del ejecutable (fallback)
        chrome_path = None
        try:
            chrome_path = uc.find_chrome_executable()
        except Exception:
            chrome_path = None

        if chrome_path and os.path.exists(chrome_path):
            try:
                flags = 0
                if hasattr(subprocess, 'CREATE_NO_WINDOW'):
                    flags |= subprocess.CREATE_NO_WINDOW
                if hasattr(subprocess, 'DETACHED_PROCESS'):
                    flags |= subprocess.DETACHED_PROCESS

                escaped = chrome_path.replace("'", "''")
                cmd = [
                    "powershell",
                    "-NoProfile",
                    "-Command",
                    f"(Get-Item '{escaped}').VersionInfo.ProductVersion"
                ]
                out = subprocess.check_output(
                    cmd,
                    stderr=subprocess.STDOUT,
                    creationflags=flags,
                    text=True
                ).strip()
                m = re.search(r'(\d+)\.', out or "")
                if m:
                    return int(m.group(1))
            except Exception:
                pass
    except Exception as e:
        log(f"⚠ No se pudo detectar versión de Chrome: {e}")
    return None

class SeleniumManager:
    def __init__(self):
        self.driver = None
        self.ready = False
        self.lock = threading.Lock()
        self.last_api_time = 0
        self.last_error = ""
        self._chrome_profile_dirs = []
        log("SeleniumManager creado")

    def _crear_perfil_chrome_temp(self):
        perfil = tempfile.mkdtemp(prefix="ug_exam_chrome_")
        self._chrome_profile_dirs.append(perfil)
        return perfil

    def _limpiar_perfiles_chrome_temp(self):
        while self._chrome_profile_dirs:
            perfil = self._chrome_profile_dirs.pop()
            try:
                shutil.rmtree(perfil, ignore_errors=True)
            except Exception:
                pass

    def inicializar(self):
        self.last_error = ""
        self._limpiar_perfiles_chrome_temp()

        # 1. Intentar primero conexión directa HTTP (rápida y sin requerir navegador si el endpoint está disponible)
        try:
            import urllib.request
            req_ping = urllib.request.Request(
                f"{API_BASE_URL}?action=ping",
                headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'}
            )
            with urllib.request.urlopen(req_ping, timeout=4) as resp_ping:
                raw_ping = resp_ping.read().decode('utf-8', errors='ignore')
                if 'success' in raw_ping and '{' in raw_ping:
                    self.ready = True
                    self.direct_mode = True
                    log("✓ Conexión directa HTTP exitosa (modo rápido activo)")
                    return True
        except Exception as e_direct:
            log(f"Intento HTTP directo: {e_direct}. Intentando mediante navegador...")

        if not SELENIUM_AVAILABLE:
            self.last_error = "Selenium/ChromeDriver no está disponible y la conexión directa no respondió con éxito."
            log(f"✗ {self.last_error}")
            return False

        chrome_major = detectar_chrome_major_version()

        # Preferir headless "new" en Chrome moderno; fallback a headless "old" si falla.
        headless_order = ["new", "old"]
        try:
            if chrome_major and int(chrome_major) < 109:
                headless_order = ["old", "new"]
        except Exception:
            headless_order = ["new", "old"]

        def _crear_options_chrome(headless_mode: str):
            # uc.Chrome() modifica internamente el objeto options, por eso
            # en cada intento/reintento se debe crear una instancia nueva.
            opts = uc.ChromeOptions()
            # Al ejecutar como admin, Chrome requiere user-data-dir dedicado.
            perfil_chrome = self._crear_perfil_chrome_temp()
            opts.add_argument(f'--user-data-dir={perfil_chrome}')
            if str(headless_mode).lower() == "new":
                opts.add_argument('--headless=new')
            else:
                opts.add_argument('--headless')
            # Flags de estabilidad/compatibilidad.
            opts.add_argument('--window-size=1280,800')
            opts.add_argument('--disable-gpu')
            opts.add_argument('--no-sandbox')
            opts.add_argument('--disable-dev-shm-usage')
            opts.add_argument('--no-first-run')
            opts.add_argument('--no-default-browser-check')
            # Algunos entornos requieren este flag para evitar "cannot connect to chrome".
            opts.add_argument('--remote-allow-origins=*')
            return opts

        def _crear_driver(headless_mode: str):
            def _uc_chrome(version_main=None):
                if version_main:
                    return uc.Chrome(
                        options=_crear_options_chrome(headless_mode),
                        version_main=version_main,
                        use_subprocess=True
                    )
                return uc.Chrome(
                    options=_crear_options_chrome(headless_mode),
                    use_subprocess=True
                )

            try:
                if chrome_major:
                    log(f"Chrome detectado (major={chrome_major}) - ajustando ChromeDriver (headless={headless_mode})...")
                    return _uc_chrome(version_main=chrome_major)
                log(f"Chrome (major desconocido) - iniciando ChromeDriver (headless={headless_mode})...")
                return _uc_chrome()
            except Exception as e:
                # Fallback: si hay mismatch, Selenium suele incluir la versión actual del navegador en el mensaje.
                msg = str(e)
                m = re.search(r'Current browser version is (\\d+)\\.', msg)
                retry_major = None
                if m:
                    retry_major = int(m.group(1))
                elif chrome_major:
                    retry_major = chrome_major
                else:
                    retry_major = detectar_chrome_major_version()

                if retry_major:
                    log(f"Reintentando ChromeDriver con version_main={retry_major} (compatibilidad, headless={headless_mode})...")
                    return _uc_chrome(version_main=retry_major)
                raise

        last_exc = None
        for headless_mode in headless_order:
            try:
                log(f"Iniciando Chrome (headless={headless_mode})...")
                self.driver = _crear_driver(headless_mode)
                self.driver.set_page_load_timeout(30)

                log("Chrome iniciado, resolviendo challenge...")
                self.driver.get(API_BASE_URL + "?action=ping")
                time.sleep(3)

                body = self.driver.find_element(By.TAG_NAME, "body").text
                log(f"Respuesta ping: {body[:50]}...")

                if 'success' in body:
                    self.ready = True
                    log("✓ Selenium listo")
                    return True

                # Si el challenge no se resolvió, probamos el otro modo headless antes de fallar.
                self.last_error = "El servidor respondió pero no devolvió 'success' (challenge no resuelto)."
                log("✗ Challenge no resuelto")
                last_exc = RuntimeError(self.last_error)
                self.cerrar()
            except Exception as e:
                last_exc = e
                self.last_error = str(e)
                log_error(e)
                self.cerrar()

        if last_exc and not self.last_error:
            self.last_error = str(last_exc)
        return False

    def api_call(self, action, datos=None):
        if not self.ready:
            return {'success': False, 'error': 'No conectado al servidor'}

        # Si estamos en modo directo o si el driver no está disponible, usar HTTP directo
        if getattr(self, 'direct_mode', False) or not self.driver:
            try:
                import urllib.request
                url = f"{API_BASE_URL}?action={action}"
                headers = {
                    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Content-Type': 'application/json'
                }
                if datos:
                    payload = json.dumps(datos).encode('utf-8')
                    req = urllib.request.Request(url, data=payload, headers=headers, method='POST')
                else:
                    req = urllib.request.Request(url, headers=headers, method='GET')

                with urllib.request.urlopen(req, timeout=15) as resp:
                    raw = resp.read().decode('utf-8', errors='ignore').strip()
                    if raw.startswith('<'):
                        m = re.search(r'\{.*\}', raw, re.DOTALL)
                        if m:
                            raw = m.group()
                    return json.loads(raw)
            except Exception as e:
                log_error(e)
                if not self.driver:
                    return {'success': False, 'error': str(e)}

        with self.lock:
            try:
                now = time.time()
                if now - self.last_api_time < 0.2:
                    time.sleep(0.2)
                self.last_api_time = time.time()

                url = f"{API_BASE_URL}?action={action}"

                if datos:
                    json_str = json.dumps(datos).replace("'", "\\'").replace("\n", "\\n")
                    script = f"""
                        return fetch(\"{url}\", {{
                            method: 'POST',
                            headers: {{'Content-Type': 'application/json'}},
                            body: '{json_str}'
                        }}).then(r => r.text()).catch(e => 'ERROR:' + e);
                    """
                    result = self.driver.execute_script(script)
                else:
                    self.driver.get(url)
                    time.sleep(0.5)
                    result = self.driver.find_element(By.TAG_NAME, "body").text

                log(f"Respuesta {action}: {str(result)[:100]}...")

                if result and str(result).startswith('<'):
                    match = re.search(r'\{.*\}', str(result), re.DOTALL)
                    if match:
                        result = match.group()

                data = json.loads(result)
                return data
            except Exception as e:
                log_error(e)
                return {'success': False, 'error': str(e)}

    def cerrar(self):
        log("Cerrando Selenium...")
        if self.driver:
            try:
                self.driver.quit()
            except:
                pass
        self.driver = None
        self.ready = False
        self._limpiar_perfiles_chrome_temp()

selenium_mgr = SeleniumManager()

# =======================================================
#  FUNCIONES DE SEGURIDAD
# =======================================================
CHAT_PATTERNS = [
    "whatsapp", "telegram", "discord", "slack", "teams", "ms-teams",
    "skype", "signal", "messenger", "line", "wechat",
    "kakaotalk", "viber",
    "anydesk", "teamviewer", "ammyy", "supremo", "rustdesk",
    "aeroadmin", "splashtop", "logmein", "parsec", "remotepc",
    "ultraviewer", "getscreen", "iperius", "radmin", "nomachine",
    "screenconnect", "connectwise", "dwagent", "dwservice",
    "remoteutilities", "rutserv", "tightvnc", "realvnc", "vnc",
    # Otros remotos comunes (best-effort)
    "todesk", "sunlogin", "anyviewer", "awesun", "zohoassist",
    "gotoassist", "dameware", "bomgar", "beyondtrust",
]

MEDIA_PLAYER_PATTERNS = [
    "vlc", "wmplayer", "mediaplayer", "microsoft.media.player",
    "video.ui", "movies", "groove", "music.ui", "zunemusic",
    "spotify", "itunes", "aimp", "winamp", "foobar2000",
    "potplayer", "kmplayer", "mpc-hc", "mpc-be", "gom",
    "smplayer", "mpv", "kodi", "plex", "quicktime"
]

CAPTURA_PATTERNS = [
    "snippingtool", "snipandsketch", "screensketch", "screenclippinghost"
]

BLOQUEO_PATRONES_PSUTIL = CHAT_PATTERNS + MEDIA_PLAYER_PATTERNS + CAPTURA_PATTERNS

# =======================================================
#  SEGURIDAD: CONTROL REMOTO (deteccion + bloqueo)
# =======================================================
# Nota: muchas apps de control remoto (p.ej. AnyDesk instalado como servicio)
# corren en Session 0 y/o como SYSTEM. Un proceso sin privilegios NO puede
# detenerlas. En ese caso la app debe bloquear el inicio del examen.
# Configuración:
# - Si False: se bloquea SOLO si hay control remoto en sesión de usuario (Session != 0).
#   Esto permite continuar cuando queda solo el servicio (Session 0) que no se puede cerrar sin admin.
# - Si True: se bloquea también por servicios (más estricto).
#   Nota: si la app se ejecuta como Administrador, se activa automáticamente en MAIN.
BLOQUEAR_SERVICIOS_CONTROL_REMOTO = False

REMOTE_CONTROL_PATTERNS = [
    "anydesk", "ad_svc", "ad_service",
    "teamviewer", "teamviewer_service", "tv_w32", "tv_x64",
    "rustdesk",
    "supremo", "ammyy", "aeroadmin",
    "screenconnect", "connectwise", "connectwisecontrol",
    "dwagent", "dwservice",
    "splashtop", "logmein",
    "parsec", "parsecd",
    "remotepc", "rpcservice",
    "ultraviewer",
    "getscreen",
    "iperius",
    "radmin",
    "nomachine", "nxservice", "nxplayer",
    "tightvnc", "realvnc", "vnc", "winvnc", "vncviewer", "tvnserver", "tvnviewer",
    "remoting_host",
    "remoteutilities", "rutserv",
    "todesk", "sunlogin", "anyviewer", "awesun", "zohoassist",
    "gotoassist", "dameware", "bomgar", "beyondtrust",
]

# Patrones orientados a servicios (Stop-Service). Evitar cadenas genéricas tipo "remote" para no afectar servicios del sistema.
REMOTE_CONTROL_SERVICE_PATTERNS = [
    "anydesk", "teamviewer", "rustdesk",
    "supremo", "ammyy", "aeroadmin",
    "screenconnect", "connectwise",
    "dwagent", "dwservice",
    "splashtop", "logmein",
    "parsec", "remotepc",
    "ultraviewer", "getscreen",
    "iperius", "radmin",
    "nomachine", "tightvnc", "realvnc", "vnc",
    "remoteutilities",
    "todesk", "sunlogin", "anyviewer", "awesun", "zohoassist",
    "gotoassist", "dameware", "bomgar", "beyondtrust",
]

# Lista de ejecutables típicos de control remoto para taskkill (best-effort).
# Nota: taskkill sin admin NO podrá cerrar servicios/SYSTEM (Session 0).
REMOTE_CONTROL_TARGETS_TASKKILL = [
    "AnyDesk.exe", "AnyDeskMSI.exe", "ad_svc.exe", "ad_service.exe",
    "TeamViewer.exe", "TeamViewer_Service.exe", "tv_w32.exe", "tv_x64.exe",
    "RustDesk.exe", "rustdesk.exe",
    "Supremo.exe", "supremo.exe", "SupremoService.exe",
    "Ammyy.exe", "AA_v3.exe", "AMMYY_Admin.exe",
    "AeroAdmin.exe", "aeroadmin.exe",
    "ScreenConnect.ClientService.exe", "ScreenConnect.Client.exe",
    "ConnectWiseControl.Client.exe", "ConnectWiseControl.ClientService.exe",
    "DWAgent.exe", "DWService.exe",
    "SplashtopStreamer.exe", "Splashtop.exe", "SRService.exe",
    "LogMeIn.exe", "LMIGuardianSvc.exe", "LogMeInClient.exe",
    "parsecd.exe", "parsec.exe",
    "RemotePC.exe", "RPCService.exe",
    "UltraViewer.exe", "ultraviewer.exe", "UltraViewer_Service.exe",
    "GetScreen.exe", "getscreen.exe",
    "Iperius.exe", "IperiusRemote.exe",
    "Radmin.exe", "rserver3.exe", "Radmin_Server.exe",
    "NoMachine.exe", "nxservice.exe", "nxplayer.exe",
    "vncviewer.exe", "winvnc.exe", "tvnviewer.exe", "tvnserver.exe",
    "remoting_host.exe",
    # Otros remotos comunes (best-effort)
    "ToDesk.exe", "todesk.exe",
    "SunloginClient.exe", "SunloginClientService.exe",
    "AnyViewer.exe", "AnyViewer_Service.exe",
    "AweSun.exe", "AweSunService.exe",
    "ZohoAssist.exe", "ZohoAssistAgent.exe",
    "g2ax_host.exe", "g2ax_host_service.exe",
    "bomgar-scc.exe", "bomgar-rep.exe",
]

def _win_pid_to_session_id(pid: int):
    if sys.platform != 'win32':
        return None
    try:
        sid = wintypes.DWORD()
        ok = ctypes.windll.kernel32.ProcessIdToSessionId(int(pid), ctypes.byref(sid))
        return int(sid.value) if ok else None
    except Exception:
        return None

def listar_procesos_control_remoto():
    """
    Retorna una lista de procesos potencialmente de control remoto.
    Cada item: {'pid': int, 'name': str, 'session': int|None}
    """
    if sys.platform != 'win32':
        return []

    detectados = []
    if PSUTIL_AVAILABLE:
        for proc in psutil.process_iter(['pid', 'name', 'cmdline']):
            try:
                pid = int(proc.info.get('pid') or 0)
                nombre = str(proc.info.get('name') or "")
                nombre_l = nombre.lower()
                cmdline_l = " ".join(proc.info.get('cmdline') or []).lower()
                if not nombre_l:
                    continue
                if any(pat in nombre_l or pat in cmdline_l for pat in REMOTE_CONTROL_PATTERNS):
                    detectados.append({
                        'pid': pid,
                        'name': nombre,
                        'session': _win_pid_to_session_id(pid)
                    })
            except (psutil.NoSuchProcess, psutil.ZombieProcess):
                continue
            except (psutil.AccessDenied, Exception):
                # Con permisos limitados puede fallar cmdline; al menos evaluamos el nombre
                try:
                    pid = int(proc.pid)
                    nombre = str(proc.name() or "")
                    if nombre and any(pat in nombre.lower() for pat in REMOTE_CONTROL_PATTERNS):
                        detectados.append({
                            'pid': pid,
                            'name': nombre,
                            'session': _win_pid_to_session_id(pid)
                        })
                except Exception:
                    continue
    else:
        # Fallback muy basico: tasklist (sin cmdline)
        try:
            out = subprocess.check_output(["tasklist", "/FO", "CSV"], text=True, creationflags=_win_creation_flags())
            for line in (out or "").splitlines():
                low = line.lower()
                if any(pat in low for pat in REMOTE_CONTROL_PATTERNS):
                    # CSV: "Image Name","PID",...
                    parts = [p.strip().strip('"') for p in line.split(",")]
                    if len(parts) >= 2:
                        nombre = parts[0]
                        try:
                            pid = int(parts[1])
                        except Exception:
                            pid = 0
                        detectados.append({'pid': pid, 'name': nombre, 'session': _win_pid_to_session_id(pid)})
        except Exception:
            pass

    # Dedupe por pid
    uniq = {}
    for d in detectados:
        pid = int(d.get('pid') or 0)
        if pid <= 0:
            continue
        uniq[pid] = d
    return list(uniq.values())

def filtrar_procesos_control_remoto_bloqueantes(detectados):
    """
    Filtra los procesos que deben bloquear el inicio.
    Por defecto NO bloquea Session 0 (servicios), porque no se pueden cerrar sin admin.
    """
    if not detectados:
        return []
    if BLOQUEAR_SERVICIOS_CONTROL_REMOTO:
        return [d for d in detectados if isinstance(d, dict)]

    bloqueantes = []
    for d in detectados:
        if not isinstance(d, dict):
            continue
        sid = d.get('session', None)
        # Si no podemos determinar la sesión, asumimos que es bloqueante (modo seguro).
        if sid is None:
            bloqueantes.append(d)
            continue
        try:
            if int(sid) != 0:
                bloqueantes.append(d)
        except Exception:
            bloqueantes.append(d)
    return bloqueantes

def detener_servicios_control_remoto_admin():
    """
    Best-effort: intenta detener servicios de control remoto (Session 0) usando PowerShell.
    Requiere ejecutar la app como Administrador.
    Retorna lista de nombres de servicios detectados (haya podido detenerlos o no).
    """
    if sys.platform != 'win32':
        return []
    if not is_windows_admin():
        return []

    patterns = [p for p in (REMOTE_CONTROL_SERVICE_PATTERNS or []) if isinstance(p, str) and p.strip()]
    if not patterns:
        return []

    try:
        # Construir array PowerShell con patrones (hardcoded, sin entrada de usuario).
        arr = ", ".join([f"'{p.lower()}'" for p in patterns])
        ps = (
            "$ErrorActionPreference='SilentlyContinue';"
            f"$patterns=@({arr});"
            "$svcs = Get-Service | Where-Object {"
            "  $n=$_.Name.ToLower(); $d=$_.DisplayName.ToLower();"
            "  ($patterns | Where-Object { $n -like ('*' + $_ + '*') -or $d -like ('*' + $_ + '*') }).Count -gt 0"
            "};"
            "foreach($s in $svcs){"
            "  try { if($s.Status -ne 'Stopped'){ Stop-Service -Name $s.Name -Force -ErrorAction Stop } } catch {};"
            "  $s.Name"
            "}"
        )
        out = subprocess.check_output(
            ["powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", ps],
            stderr=subprocess.STDOUT,
            creationflags=_win_creation_flags(),
            timeout=3,
            text=True
        )
        names = [ln.strip() for ln in (out or "").splitlines() if ln.strip()]
        for n in names[:20]:
            log(f"   ✓ Servicio control remoto detectado: {n}")
        if len(names) > 20:
            log(f"   ... y {len(names) - 20} servicio(s) más")
        return names
    except subprocess.TimeoutExpired:
        log("⚠ Timeout deteniendo servicios de control remoto (PowerShell)")
    except Exception as e:
        log(f"⚠ Error deteniendo servicios de control remoto: {e}")
    return []

def intentar_cerrar_control_remoto_con_admin():
    """
    Intenta cerrar procesos/servicios de control remoto con privilegios de administrador.
    Retorna lista de procesos que quedan activos.
    """
    if sys.platform != 'win32':
        return []

    # 0) Intentar detener servicios (mejor para AnyDesk/TeamViewer instalados como servicio)
    try:
        detener_servicios_control_remoto_admin()
    except Exception:
        pass

    if not PSUTIL_AVAILABLE:
        # Sin psutil: best-effort por nombre/PID.
        try:
            cmd = ["taskkill", "/F", "/T"]
            for exe in REMOTE_CONTROL_TARGETS_TASKKILL:
                cmd.extend(["/IM", exe])
            subprocess.run(
                cmd,
                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                creationflags=_win_creation_flags(), timeout=1.0
            )
        except Exception:
            pass
        return listar_procesos_control_remoto()

    # 1) Matar por PID todo lo detectado (incluye Session 0)
    detectados = listar_procesos_control_remoto()
    for d in detectados:
        pid = int(d.get('pid') or 0)
        if pid <= 0:
            continue
        try:
            p = psutil.Process(pid)
            try:
                p.kill()
            except psutil.AccessDenied:
                _taskkill_pid(pid)
            except Exception:
                _taskkill_pid(pid)
        except (psutil.NoSuchProcess, psutil.ZombieProcess):
            continue
        except Exception:
            _taskkill_pid(pid)

    # 2) Best-effort por nombre (incluye árboles /T)
    try:
        cmd = ["taskkill", "/F", "/T"]
        for exe in REMOTE_CONTROL_TARGETS_TASKKILL:
            cmd.extend(["/IM", exe])
        subprocess.run(
            cmd,
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
            creationflags=_win_creation_flags(), timeout=1.0
        )
    except Exception:
        pass

    return listar_procesos_control_remoto()

def intentar_cerrar_control_remoto_sin_admin():
    """
    Intenta cerrar procesos de control remoto que sean cerrables sin admin.
    Retorna lista de procesos que quedan activos.
    """
    if sys.platform != 'win32':
        return []

    if not PSUTIL_AVAILABLE:
        # Sin psutil no tenemos buena forma de matar por PID; hacemos best-effort por nombre
        try:
            # Esto probablemente no cerrara Session 0; igual sirve para procesos de usuario.
            cmd = ["taskkill", "/F", "/T"]
            for exe in REMOTE_CONTROL_TARGETS_TASKKILL:
                cmd.extend(["/IM", exe])
            subprocess.run(cmd,
                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                           creationflags=_win_creation_flags(), timeout=0.5)
        except Exception:
            pass
        return listar_procesos_control_remoto()

    # Hacer un par de pasadas: algunas apps se relanzan rápido (watchdog propio).
    for _ in range(2):
        # 1) Intentar matar procesos detectados (best-effort, puede fallar por permisos)
        detectados = listar_procesos_control_remoto()
        for d in detectados:
            pid = int(d.get('pid') or 0)
            if pid <= 0:
                continue
            try:
                p = psutil.Process(pid)
                try:
                    p.kill()
                except psutil.AccessDenied:
                    _taskkill_pid(pid)
                except Exception:
                    _taskkill_pid(pid)
            except (psutil.NoSuchProcess, psutil.ZombieProcess):
                continue
            except Exception:
                _taskkill_pid(pid)

        # 2) Best-effort por nombre (a veces queda un helper sin cmdline accesible)
        try:
            cmd = ["taskkill", "/F", "/T"]
            for exe in REMOTE_CONTROL_TARGETS_TASKKILL:
                cmd.extend(["/IM", exe])
            subprocess.run(
                cmd,
                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                creationflags=_win_creation_flags(), timeout=0.6
            )
        except Exception:
            pass

        # Dar tiempo a Windows a terminar procesos antes de re-scan
        time.sleep(0.15)

    return listar_procesos_control_remoto()

def cerrar_control_remoto_fast():
    """
    Cierre ligero (sin lanzar taskkill masivo). Pensado para watchdog permanente.
    Retorna lista de procesos restantes detectados.
    """
    if sys.platform != 'win32':
        return []

    detectados = listar_procesos_control_remoto()
    if not detectados:
        return []

    if PSUTIL_AVAILABLE:
        for d in detectados:
            pid = int(d.get('pid') or 0)
            if pid <= 0:
                continue
            try:
                p = psutil.Process(pid)
                try:
                    p.kill()
                except psutil.AccessDenied:
                    _taskkill_pid(pid)
                except Exception:
                    _taskkill_pid(pid)
            except (psutil.NoSuchProcess, psutil.ZombieProcess):
                continue
            except Exception:
                _taskkill_pid(pid)
    else:
        # Sin psutil: intentamos best-effort por nombre sobre lo detectado.
        try:
            nombres = sorted({str(d.get('name') or '') for d in detectados if isinstance(d, dict) and d.get('name')})
            if nombres:
                cmd = ["taskkill", "/F", "/T"]
                for n in nombres:
                    cmd.extend(["/IM", n])
                subprocess.run(
                    cmd,
                    stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                    creationflags=_win_creation_flags(), timeout=0.7
                )
        except Exception:
            pass

    time.sleep(0.05)
    return listar_procesos_control_remoto()

def intentar_cerrar_control_remoto():
    """
    Wrapper: si la app corre como Administrador, también intenta cerrar servicios (Session 0).
    """
    if sys.platform != 'win32':
        return []
    if is_windows_admin():
        return intentar_cerrar_control_remoto_con_admin()
    return intentar_cerrar_control_remoto_sin_admin()

def _win_creation_flags():
    flags = 0
    if hasattr(subprocess, 'CREATE_NO_WINDOW'):
        flags = subprocess.CREATE_NO_WINDOW
    if hasattr(subprocess, 'DETACHED_PROCESS'):
        flags |= subprocess.DETACHED_PROCESS
    return flags

def _taskkill_pid(pid):
    """Intenta cerrar un proceso por PID con taskkill /T (incluye hijos)."""
    if sys.platform != 'win32':
        return False
    try:
        subprocess.run(
            ["taskkill", "/F", "/T", "/PID", str(int(pid))],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            creationflags=_win_creation_flags(),
            timeout=0.5
        )
        return True
    except:
        return False

def cerrar_menu_proyeccion():
    """Cierra el menú de proyección de Windows (Win+P) si está abierto."""
    if sys.platform != 'win32':
        return

    try:
        flags = 0
        if hasattr(subprocess, 'CREATE_NO_WINDOW'):
            flags = subprocess.CREATE_NO_WINDOW
        if hasattr(subprocess, 'DETACHED_PROCESS'):
            flags |= subprocess.DETACHED_PROCESS

        procesos_proyeccion = [
            "DisplaySwitchUIHost.exe",
            "MobilityCenter.exe",
        ]

        for proc in procesos_proyeccion:
            subprocess.run(
                ["taskkill", "/F", "/IM", proc],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                creationflags=flags,
                timeout=0.5
            )
    except:
        pass

def cerrar_chats_psutil():
    if not PSUTIL_AVAILABLE or sys.platform != 'win32':
        return
    for proc in psutil.process_iter(['pid', 'name', 'cmdline']):
        try:
            nombre = (proc.info.get('name') or "").lower()
            cmdline = " ".join(proc.info.get('cmdline') or []).lower()
            if any(pat in nombre or pat in cmdline for pat in BLOQUEO_PATRONES_PSUTIL):
                pid = int(proc.info.get('pid') or 0)
                cerrado = False
                try:
                    proc.kill()
                    cerrado = True
                except psutil.AccessDenied:
                    cerrado = _taskkill_pid(pid)
                except psutil.NoSuchProcess:
                    cerrado = True
                except:
                    cerrado = _taskkill_pid(pid)

                if cerrado:
                    log(f"   ✓ App no permitida cerrada: {nombre} (PID: {pid})")
                else:
                    log(f"   ⚠ No se pudo cerrar app no permitida: {nombre} (PID: {pid})")
        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
            continue
        except Exception as e:
            log(f"chat kill error: {e}")

def cerrar_multimedia_uwp():
    """Cierra reproductores multimedia UWP alojados en ApplicationFrameHost."""
    if sys.platform != 'win32':
        return

    flags = 0
    if hasattr(subprocess, 'CREATE_NO_WINDOW'):
        flags = subprocess.CREATE_NO_WINDOW
    if hasattr(subprocess, 'DETACHED_PROCESS'):
        flags |= subprocess.DETACHED_PROCESS

    try:
        ps = (
            "Get-Process -Name ApplicationFrameHost -ErrorAction SilentlyContinue | "
            "Where-Object { $_.MainWindowTitle -match "
            "'^(Pel[ií]culas|Movies|Media\\s*Player|Reproductor\\s+multimedia|Groove|M[uú]sica|Music)(\\b|\\s|$)' } | "
            "Stop-Process -Force"
        )
        subprocess.run(
            ["powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", ps],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            creationflags=flags,
            timeout=0.5
        )
    except:
        pass

def cerrar_apps_no_permitidas():
    if sys.platform != 'win32':
        return

    targets = [
        "WINWORD.EXE", "EXCEL.EXE", "POWERPNT.EXE",
        "Microsoft.Photos.exe", "PhotosApp.exe", "Photos.exe",
        "AcroRd32.exe", "Acrobat.exe", "FoxitPDFReader.exe", "SumatraPDF.exe",
        "SnippingTool.exe", "SnipAndSketch.exe", "ScreenSketch.exe", "ScreenClippingHost.exe",
        "Calculator.exe", "calc.exe", "notepad.exe",
        "WhatsApp.exe", "WhatsAppBeta.exe", "WhatsAppDesktop.exe",
        "Telegram.exe", "Discord.exe",
        "Slack.exe", "slack.exe",
        "Teams.exe", "ms-teams.exe",
        "Skype.exe", "Signal.exe", "Messenger.exe", "LINE.exe",
        "WeChat.exe",
        "AnyDesk.exe", "anydesk.exe",
        "AnyDeskMSI.exe", "ad_svc.exe", "ad_service.exe",
        "TeamViewer.exe", "teamviewer.exe", "TeamViewer_Service.exe", "tv_w32.exe", "tv_x64.exe",
        "Ammyy.exe", "AA_v3.exe", "AMMYY_Admin.exe",
        "Supremo.exe", "supremo.exe", "SupremoService.exe",
        "RustDesk.exe", "rustdesk.exe",
        "ScreenConnect.ClientService.exe", "ScreenConnect.Client.exe", "ConnectWiseControl.Client.exe",
        "DWAgent.exe", "DWService.exe",
        "AeroAdmin.exe", "aeroadmin.exe",
        "SplashtopStreamer.exe", "Splashtop.exe", "SRService.exe",
        "LogMeIn.exe", "LMIGuardianSvc.exe", "LogMeInClient.exe",
        "parsecd.exe", "parsec.exe",
        "RemotePC.exe", "RPCService.exe",
        "UltraViewer.exe", "ultraviewer.exe", "UltraViewer_Service.exe",
        "GetScreen.exe", "getscreen.exe",
        "Iperius.exe", "IperiusRemote.exe",
        "Radmin.exe", "rserver3.exe", "Radmin_Server.exe",
        "nxplayer.exe", "nxservice.exe", "NoMachine.exe",
        "vncviewer.exe", "winvnc.exe", "tvnviewer.exe", "tvnserver.exe",
        "remoting_host.exe",
        "vlc.exe", "wmplayer.exe", "MediaPlayer.exe", "Microsoft.Media.Player.exe",
        "Video.UI.exe", "MoviesTV.exe", "GrooveMusic.exe", "Music.UI.exe", "ZuneMusic.exe",
        "Spotify.exe", "SpotifyWebHelper.exe", "iTunes.exe",
        "AIMP.exe", "Winamp.exe", "foobar2000.exe",
        "PotPlayerMini64.exe", "PotPlayerMini.exe", "KMPlayer.exe",
        "mpc-hc64.exe", "mpc-hc.exe", "mpc-be64.exe", "mpc-be.exe",
        "GOM.exe", "GOM64.exe", "SMPlayer.exe", "mpv.exe",
        "Kodi.exe", "Plex.exe", "Plex Media Player.exe",
        "QuickTimePlayer.exe",
    ]

    try:
        cmd = ["taskkill", "/F", "/T"]
        for t in targets:
            cmd.extend(["/IM", t])

        subprocess.run(
            cmd,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            creationflags=_win_creation_flags(),
            timeout=0.5
        )
    except:
        pass

    cerrar_chats_psutil()
    cerrar_multimedia_uwp()

def cerrar_fotos_inmediato():
    if sys.platform != 'win32':
        return

    flags = 0
    if hasattr(subprocess, 'CREATE_NO_WINDOW'):
        flags = subprocess.CREATE_NO_WINDOW
    if hasattr(subprocess, 'DETACHED_PROCESS'):
        flags |= subprocess.DETACHED_PROCESS

    try:
        cmd = ["taskkill", "/F", "/T"]
        for t in ["Microsoft.Photos.exe", "PhotosApp.exe", "Photos.exe"]:
            cmd.extend(["/IM", t])
        subprocess.run(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                      creationflags=flags, timeout=0.5)
    except:
        pass

    try:
        ps = (
            "Get-Process -Name ApplicationFrameHost -ErrorAction SilentlyContinue | "
            "Where-Object { $_.MainWindowTitle -match '^(Fotos|Photos)(\\b|\\s|$)' } | "
            "Stop-Process -Force"
        )
        subprocess.run(
            ["powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", ps],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            creationflags=flags,
            timeout=0.5
        )
    except:
        pass

def bloquear_teclas():
    if not KEYBOARD_AVAILABLE:
        log("⚠ 'keyboard' lib no disponible. No se bloquearán teclas.")
        return

    log(">>> Bloqueando teclas...")
    
    keys_to_block = [
        'windows', 'win', 'left windows', 'right windows',
        'home',
        'print screen', 'printscreen', 'prt sc', 'prtsc', 'prtscn', 'snapshot',
        'prntscrn', 'prnt scrn',
        'impr pant', 'imprpant', 'imprimir pantalla', 'captura de pantalla'
    ]
    
    blocked_count = 0
    for k in keys_to_block:
        try:
            keyboard.block_key(k)
            blocked_count += 1
        except Exception:
            pass
    
    # Refuerzo: bloquear usando códigos virtuales de teclas sensibles
    for vk in (91, 92, 44):  # VK_LWIN, VK_RWIN, VK_SNAPSHOT
        try:
            keyboard.block_key(vk)
            blocked_count += 1
        except Exception:
            pass
    
    hotkeys = [
        ('alt+tab', lambda: None),
        ('ctrl+tab', lambda: None),
        ('ctrl+p', lambda: None),
        ('alt+f4', lambda: None),
        ('ctrl+esc', lambda: None),
        ('win+d', lambda: None),
        ('win+e', lambda: None),
        ('win+r', lambda: None),
        ('win+tab', lambda: None),
        ('win+p', lambda: None),
        ('win+k', lambda: None),
        ('win+shift+s', lambda: None),
        ('win+print screen', lambda: None),
        ('win+prtsc', lambda: None),
        ('windows+p', lambda: None),
        ('windows+k', lambda: None),
        ('windows+shift+s', lambda: None),
        ('windows+prtsc', lambda: None),
        ('left windows+p', lambda: None),
        ('right windows+p', lambda: None),
        ('left windows+shift+s', lambda: None),
        ('right windows+shift+s', lambda: None),
        ('print screen', lambda: None),
        ('prtsc', lambda: None),
        ('prtscn', lambda: None),
        ('snapshot', lambda: None),
        ('alt+print screen', lambda: None),
        ('alt+prtsc', lambda: None),
        ('alt+prtscn', lambda: None),
        ('ctrl+print screen', lambda: None),
        ('ctrl+prtsc', lambda: None),
        ('shift+print screen', lambda: None),
        ('shift+prtsc', lambda: None),
    ]
    
    hotkey_count = 0
    for hk, callback in hotkeys:
        try:
            keyboard.add_hotkey(hk, callback, suppress=True)
            hotkey_count += 1
        except Exception:
            pass

    # Swallow pulsaciones individuales de la tecla Windows
    for hk in ('windows', 'left windows', 'right windows'):
        try:
            keyboard.add_hotkey(hk, lambda: None, suppress=True)
            hotkey_count += 1
        except Exception:
            pass
    
    log(f"   ✓ Teclas bloqueadas: {blocked_count}, Hotkeys suprimidos: {hotkey_count}")

def desbloquear_teclas():
    if not KEYBOARD_AVAILABLE:
        return
    log(">>> Desbloqueando teclas...")
    try:
        keyboard.unhook_all()
    except Exception as e:
        log(f"Error al desbloquear teclas: {e}")

# =======================================================
#  CONTROL DE NAVEGADORES (CERRAR)
# =======================================================
NAVEGADORES = [
    "firefox.exe", "msedge.exe", "opera.exe", "brave.exe", "vivaldi.exe",
    "iexplore.exe", "chromium.exe", "waterfox.exe", "palemoon.exe",
    "seamonkey.exe", "maxthon.exe", "slimbrowser.exe", "avant.exe",
    "iron.exe", "dragon.exe",
]

def cerrar_navegadores():
    if not PSUTIL_AVAILABLE:
        log("⚠ psutil no disponible, usando taskkill básico")
        _cerrar_navegadores_taskkill()
        return
    if sys.platform != 'win32':
        return
    
    log(">>> Cerrando navegadores (Chrome excluido por Selenium)...")
    for proc in psutil.process_iter(['pid', 'name']):
        try:
            nombre = proc.info['name'].lower() if proc.info['name'] else ""
            for nav in NAVEGADORES:
                if nombre == nav.lower():
                    pid = proc.info['pid']
                    try:
                        proc.terminate()
                        log(f"   ✓ Cerrado: {nombre} (PID: {pid})")
                    except psutil.AccessDenied:
                        log(f"   ⚠ Sin permisos para cerrar: {nombre} (PID: {pid})")
                    except psutil.NoSuchProcess:
                        pass
                    except Exception as e:
                        log(f"   ✗ Error cerrando {nombre}: {e}")
                    break
        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
            continue
        except Exception as e:
            log(f"Error iterando procesos: {e}")
    time.sleep(0.5)
    _forzar_cierre_navegadores()

def _forzar_cierre_navegadores():
    if not PSUTIL_AVAILABLE:
        return
    for proc in psutil.process_iter(['pid', 'name']):
        try:
            nombre = proc.info['name'].lower() if proc.info['name'] else ""
            for nav in NAVEGADORES:
                if nombre == nav.lower():
                    try:
                        proc.kill()
                    except:
                        pass
                    break
        except:
            continue

def _cerrar_navegadores_taskkill():
    if sys.platform != 'win32':
        return
    flags = 0
    if hasattr(subprocess, 'CREATE_NO_WINDOW'):
        flags = subprocess.CREATE_NO_WINDOW
    try:
        cmd = ["taskkill", "/F"]
        for nav in NAVEGADORES:
            cmd.extend(["/IM", nav])
        subprocess.run(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, creationflags=flags)
    except:
        pass

def cerrar_navegadores_nuevos():
    if not PSUTIL_AVAILABLE or sys.platform != 'win32':
        return
    for proc in psutil.process_iter(['pid', 'name', 'create_time']):
        try:
            nombre = proc.info['name'].lower() if proc.info['name'] else ""
            for nav in NAVEGADORES:
                if nombre == nav.lower():
                    try:
                        proc.kill()
                        log(f"   ✓ Navegador nuevo cerrado: {nombre} (PID: {proc.info['pid']})")
                    except:
                        pass
                    break
        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
            continue
        except:
            pass

# =======================================================
#  FUNCIONES PARA IMÁGENES
# =======================================================
def cargar_imagen_base64(base64_str, max_width=500, max_height=300):
    if not PIL_AVAILABLE or not base64_str:
        return None
    try:
        if ',' in base64_str:
            base64_str = base64_str.split(',')[1]
        image_data = base64.b64decode(base64_str)
        image = Image.open(io.BytesIO(image_data))
        width, height = image.size
        if width > max_width or height > max_height:
            ratio = min(max_width / width, max_height / height)
            new_size = (int(width * ratio), int(height * ratio))
            image = image.resize(new_size, Image.Resampling.LANCZOS)
        return ImageTk.PhotoImage(image)
    except Exception as e:
        log(f"Error img: {e}")
        return None

# =======================================================
#  APLICACIÓN
# =======================================================
class App:
    root: tk.Tk
    def __init__(self):
        self.root = None  # type: ignore
        self.frame_actual = None
        self.imagenes_cache = []
        self._app_icon_ref = None

        self._stop_fotos_watchdog = threading.Event()
        self._stop_chats_watchdog = threading.Event()
        self._stop_control_remoto_watchdog = threading.Event()
        self._stop_pantallas_watchdog = threading.Event()
        self._stop_proyeccion_watchdog = threading.Event()
        self.dialogo_activo = False
        self.bloqueo_seguridad_activo = False
        
        self.finalizando_por_pantalla = False
        self.en_pantalla_resumen = False
        self.timer_visible = True  # Para el botón Ocultar/Mostrar timer
        self._timer_id = None  # ID del timer para poder cancelarlo
        self._timer_resumen_id = None  # ID del timer del resumen
        self._tiempo_sync_en_curso = False
        self._auto_refresh_examenes_id = None
        self._vista_examenes_activa = False
        self._cargando_examenes = False
        self.btn_refrescar_examenes = None
        self.zoom_window = None  # Ventana de zoom para imágenes
        self.imagen_original_data = None  # Datos de imagen para zoom
        self._mensajeria_activa = False
        self._mensajes_poll_id = None
        self._mensajes_ultimo_hash = None
        self.lbl_mensajes_titulo = None
        self.txt_mensajes = None
        self.entry_mensaje = None
        self.btn_enviar_mensaje = None
        self.lbl_mensajes_estado = None
        self.btn_toggle_modo = None
        self.lbl_modo_badge = None

        log("App creada")

    def _watchdog_fotos_loop(self):
        while not self._stop_fotos_watchdog.is_set():
            try:
                cerrar_fotos_inmediato()
            except:
                pass
            time.sleep(0.2)

    def _watchdog_chats_loop(self):
        while not self._stop_chats_watchdog.is_set():
            try:
                cerrar_apps_no_permitidas()
            except:
                pass
            time.sleep(0.5)

    def _watchdog_control_remoto_loop(self):
        global examen_activo
        # Watchdog (sin admin): intenta cerrar AnyDesk/TeamViewer/RustDesk/etc durante el examen.
        while not self._stop_control_remoto_watchdog.is_set():
            try:
                cerrar_control_remoto_fast()
            except Exception:
                pass
            time.sleep(0.5 if examen_activo else 1.5)

    def _watchdog_proyeccion_loop(self):
        global examen_activo
        
        log(">>> Watchdog de proyección ACTIVO")
        ultimo_cierre_menu = 0.0
        
        while not self._stop_proyeccion_watchdog.is_set():
            try:
                if examen_activo:
                    ahora = time.time()
                    # Cerrar panel Win+P si aparece (sin matar procesos en bucle agresivo).
                    if (ahora - ultimo_cierre_menu) >= 0.8:
                        cerrar_menu_proyeccion()
                        ultimo_cierre_menu = ahora

                    # Solo forzar DisplaySwitch si realmente se detecta modo "solo segunda".
                    if detectar_solo_segunda_pantalla():
                        forzar_pantalla_principal(motivo="watchdog_proyeccion")
            except:
                pass
            time.sleep(0.2)
        
        log(">>> Watchdog de proyección TERMINADO")

    def _watchdog_pantallas_loop(self):
        global examen_activo, monitoreo_pantallas_activo
        
        log(">>> Watchdog de pantallas ACTIVO - Monitoreando conexiones de video...")
        
        while monitoreo_pantallas_activo:
            try:
                if self._stop_pantallas_watchdog.is_set():
                    log(">>> Watchdog de pantallas recibió señal de detención")
                    break
                
                if not self.finalizando_por_pantalla:
                    if detectar_solo_segunda_pantalla():
                        log("⚠ Detectado modo 'Solo segunda pantalla' - Forzando pantalla principal")
                        forzar_pantalla_principal(motivo="watchdog_pantallas")
                        
                        if examen_activo:
                            log(">>> Finalizando examen por uso de 'Solo segunda pantalla'...")
                            self.finalizando_por_pantalla = True
                            self.root.after(0, lambda: self.finalizar_examen_por_pantalla(2))
                        elif not self.bloqueo_seguridad_activo:
                            self.root.after(0, lambda: self.mostrar_bloqueo_pantallas(2))
                    else:
                        hay_multiples, num_displays = hay_multiples_pantallas()
                        
                        if hay_multiples:
                            log(f"⚠ ¡ALERTA! Se detectaron {num_displays} dispositivos de salida de video (examen_activo={examen_activo})")
                            
                            if examen_activo:
                                log(">>> Finalizando examen por conexión de dispositivo adicional...")
                                self.finalizando_por_pantalla = True
                                self.root.after(0, lambda n=num_displays: self.finalizar_examen_por_pantalla(n))
                            elif not self.bloqueo_seguridad_activo:
                                log(">>> Mostrando pantalla de bloqueo por dispositivo adicional...")
                                self.root.after(0, lambda n=num_displays: self.mostrar_bloqueo_pantallas(n))
                
            except Exception as e:
                log(f"Error en watchdog pantallas: {e}")
            
            intervalo = 0.3 if examen_activo else 0.5
            time.sleep(intervalo)
        
        log(">>> Watchdog de pantallas TERMINADO")

    def iniciar_watchdog_control_remoto_permanente(self):
        try:
            self._stop_control_remoto_watchdog.clear()
        except Exception:
            pass
        self._thread_control_remoto = threading.Thread(target=self._watchdog_control_remoto_loop, daemon=True)
        self._thread_control_remoto.start()
        log("✓ Watchdog de control remoto permanente iniciado")

    def iniciar_watchdog_pantallas_permanente(self):
        global monitoreo_pantallas_activo
        monitoreo_pantallas_activo = True
        
        try:
            self._stop_pantallas_watchdog.clear()
        except:
            pass
        self.finalizando_por_pantalla = False
        self._thread_pantallas = threading.Thread(target=self._watchdog_pantallas_loop, daemon=True)
        self._thread_pantallas.start()
        log("✓ Watchdog de pantallas permanente iniciado")

    def reiniciar_watchdog_pantallas(self):
        global monitoreo_pantallas_activo
        
        self.finalizando_por_pantalla = False
        
        thread_vivo = hasattr(self, '_thread_pantallas') and self._thread_pantallas.is_alive()
        
        if not thread_vivo:
            log(">>> Watchdog de pantallas no estaba corriendo, reiniciando...")
            monitoreo_pantallas_activo = True
            try:
                self._stop_pantallas_watchdog.clear()
            except:
                pass
            self._thread_pantallas = threading.Thread(target=self._watchdog_pantallas_loop, daemon=True)
            self._thread_pantallas.start()
            log("✓ Watchdog de pantallas reiniciado")
        else:
            log("✓ Watchdog de pantallas ya está corriendo (thread activo)")

    def iniciar_watchdogs_examen(self):
        global monitoreo_pantallas_activo
        monitoreo_pantallas_activo = True
        
        try:
            self._stop_fotos_watchdog.clear()
            self._stop_chats_watchdog.clear()
            self._stop_control_remoto_watchdog.clear()
            self._stop_pantallas_watchdog.clear()
            self._stop_proyeccion_watchdog.clear()
        except Exception:
            pass
        
        self._thread_fotos = threading.Thread(target=self._watchdog_fotos_loop, daemon=True)
        self._thread_chats = threading.Thread(target=self._watchdog_chats_loop, daemon=True)
        self._thread_control_remoto = threading.Thread(target=self._watchdog_control_remoto_loop, daemon=True)
        self._thread_proyeccion = threading.Thread(target=self._watchdog_proyeccion_loop, daemon=True)
        self._thread_fotos.start()
        self._thread_chats.start()
        self._thread_control_remoto.start()
        self._thread_proyeccion.start()
        
        self.reiniciar_watchdog_pantallas()
        log("✓ Watchdogs de examen iniciados (incluyendo monitoreo de pantallas y proyección)")

    def detener_watchdogs_examen(self):
        try:
            self._stop_fotos_watchdog.set()
            self._stop_chats_watchdog.set()
            self._stop_control_remoto_watchdog.set()
            self._stop_proyeccion_watchdog.set()
        except:
            pass

    # ===================================================
    #  MÉTODOS HELPER PARA DIÁLOGOS
    # ===================================================
    def mostrar_mensaje_error(self, titulo, mensaje):
        self.dialogo_activo = True
        try:
            messagebox.showerror(titulo, mensaje)
        finally:
            self.dialogo_activo = False

    def mostrar_mensaje_warning(self, titulo, mensaje):
        self.dialogo_activo = True
        try:
            messagebox.showwarning(titulo, mensaje)
        finally:
            self.dialogo_activo = False

    def mostrar_mensaje_info(self, titulo, mensaje):
        self.dialogo_activo = True
        try:
            messagebox.showinfo(titulo, mensaje)
        finally:
            self.dialogo_activo = False

    def mostrar_confirmar_si_no(self, titulo, mensaje):
        self.dialogo_activo = True
        try:
            return messagebox.askyesno(titulo, mensaje)
        finally:
            self.dialogo_activo = False

    def iniciar(self):
        log("Iniciando ventana principal...")
        set_windows_app_id(APP_ID)

        self.root = tk.Tk()
        self.root.title(APP_NAME)
        mover_a_pantalla_principal(self.root)
        self.root.minsize(900, 650)
        self.root.state('zoomed')
        self.root.configure(bg=COLORS['bg_light'])
        self.root.protocol("WM_DELETE_WINDOW", self.salir)

        # Icono
        try:
            ico_path = resource_path(os.path.join("Imagenes", "LogoUGcolor.ico"))
            png_path = resource_path(os.path.join("Imagenes", "LogoUGcolor.png"))

            if sys.platform == "win32" and os.path.exists(ico_path):
                self.root.iconbitmap(ico_path)

            if os.path.exists(png_path):
                if PIL_AVAILABLE:
                    im = Image.open(png_path).convert("RGBA")
                    self._app_icon_ref = ImageTk.PhotoImage(im)
                else:
                    self._app_icon_ref = tk.PhotoImage(file=png_path)
                self.root.iconphoto(True, self._app_icon_ref)
        except Exception as e:
            log(f"⚠ Icono warning: {e}")

        self.iniciar_watchdog_pantallas_permanente()

        if detectar_solo_segunda_pantalla():
            log("⚠ Detectado modo 'Solo segunda pantalla' al inicio - Forzando pantalla principal")
            forzar_pantalla_principal(force=True, motivo="inicio_app")
            time.sleep(0.5)

        hay_multiples, num_displays = hay_multiples_pantallas()
        
        if hay_multiples:
            self.mostrar_bloqueo_pantallas(num_displays)
        else:
            self.mostrar_splash()

        self.root.mainloop()

    def salir(self):
        global monitoreo_pantallas_activo
        log("Saliendo...")
        
        monitoreo_pantallas_activo = False
        
        try:
            self._stop_fotos_watchdog.set()
            self._stop_chats_watchdog.set()
            self._stop_control_remoto_watchdog.set()
            self._stop_pantallas_watchdog.set()
            self._stop_proyeccion_watchdog.set()
        except:
            pass

        mostrar_taskbar()
        desbloquear_teclas()
        desinstalar_hook_teclado_ll()
        selenium_mgr.cerrar()
        if self.root:
            self.root.destroy()

    def limpiar_frame(self):
        self._detener_auto_refresh_examenes()
        self.detener_mensajeria()
        # Limpiar cache de imágenes
        self.imagenes_cache = []
        
        # Desvincular eventos globales para evitar acumulación
        try:
            self.root.unbind_all("<MouseWheel>")
        except:
            pass
        
        # Destruir frame actual
        if self.frame_actual:
            self.frame_actual.destroy()
        
        # Forzar recolección de basura
        import gc
        gc.collect()
        
        # Crear nuevo frame
        self.frame_actual = tk.Frame(self.root, bg=COLORS['bg_light'])
        self.frame_actual.pack(fill='both', expand=True)
        return self.frame_actual

    # ===================================================
    #  V9.5: PANTALLA DE BLOQUEO POR MÚLTIPLES PANTALLAS
    # ===================================================
    def mostrar_bloqueo_pantallas(self, n_monitores):
        """Muestra la pantalla de bloqueo cuando hay múltiples pantallas."""
        self.bloqueo_seguridad_activo = True

        # Si hay usuario activo y examen en curso o seleccionado, reportar al docente
        try:
            ex_id = getattr(self, "examen_id_actual", None)
            est_id = usuario_actual['id'] if (usuario_actual and 'id' in usuario_actual) else None
            if ex_id and est_id:
                threading.Thread(target=lambda: selenium_mgr.api_call('notificar_seguridad', {
                    'examen_id': ex_id,
                    'estudiante_id': est_id,
                    'tipo': 'salida_video_externa',
                    'motivo': f"Bloqueo por seguridad: se detectaron {n_monitores} pantalla(s) / display(s) conectados."
                }), daemon=True).start()
        except Exception:
            pass

        frame = self.limpiar_frame()
        frame.configure(bg=COLORS['bg_white'])

        cont = tk.Frame(frame, bg=COLORS['bg_white'])
        cont.place(relx=0.5, rely=0.5, anchor='center')

        # Icono de pantalla bloqueada
        c = tk.Canvas(cont, width=180, height=140, bg=COLORS['bg_white'], highlightthickness=0)
        c.pack(pady=(0, 10))
        c.create_rectangle(35, 25, 145, 90, outline='#111827', width=3, fill='#f3f4f6')
        c.create_rectangle(70, 92, 110, 100, outline='#111827', width=2, fill='#e5e7eb')
        c.create_rectangle(60, 100, 120, 110, outline='#111827', width=2, fill='#e5e7eb')
        c.create_oval(55, 35, 125, 105, outline=COLORS['danger'], width=6)
        c.create_line(60, 100, 120, 40, fill=COLORS['danger'], width=6)

        tk.Label(cont, text="⚠️ ACCESO BLOQUEADO", font=('Calibri', 18, 'bold'),
                 fg=COLORS['danger'], bg=COLORS['bg_white']).pack(pady=(0, 10))

        msg_principal = "Se detectaron múltiples dispositivos\nde salida de video conectados."
        tk.Label(cont, text=msg_principal,
                 font=('Calibri', 12, 'bold'), fg=COLORS['text_dark'], bg=COLORS['bg_white'], justify='center').pack(pady=(0, 10))

        tk.Label(cont, text=f"Motivo: Se detectaron {n_monitores} pantallas conectadas",
                 font=('Calibri', 10), fg=COLORS['danger'], bg='#fff5f5', 
                 padx=15, pady=8, wraplength=400).pack(pady=(0, 15))

        tk.Label(cont, text="Desconecte los dispositivos adicionales\ny haga clic en 'Reintentar'.",
                 font=('Calibri', 11), fg=COLORS['secondary'], bg='#f3f4f6', 
                 padx=20, pady=15, justify='center').pack(pady=(0, 15))

        tk.Label(cont, text=f"Detectado: {n_monitores} pantalla(s) / display(s)",
                 font=('Calibri', 10), fg=COLORS['text_muted'], bg=COLORS['bg_white']).pack(pady=(0, 20))

        frame_btns = tk.Frame(cont, bg=COLORS['bg_white'])
        frame_btns.pack()

        tk.Button(frame_btns, text="🔄 Reintentar", font=('Calibri', 11, 'bold'),
                  bg=COLORS['primary'], fg='white', bd=0, padx=22, pady=8,
                  activebackground=COLORS['primary_dark'], activeforeground='white',
                  cursor='hand2', command=self.reintentar_verificacion_pantalla).pack(side='left', padx=5)

        tk.Button(frame_btns, text="❌ Cerrar App", font=('Calibri', 11, 'bold'),
                  bg=COLORS['danger'], fg='white', bd=0, padx=22, pady=8,
                  activebackground='#b91c1c', activeforeground='white',
                  cursor='hand2', command=self.salir).pack(side='left', padx=5)

    def reintentar_verificacion_pantalla(self):
        log(">>> Reintentando verificación de pantalla...")
        
        hay_multiples, num_displays = hay_multiples_pantallas()
        
        if not hay_multiples:
            log("✓ Verificación exitosa - solo 1 pantalla")
            self.bloqueo_seguridad_activo = False
            self.mostrar_splash()
        else:
            log(f"✗ Verificación fallida: {num_displays} pantallas detectadas")
            self.mostrar_bloqueo_pantallas(num_displays)

    # ===================================================
    #  V9.7: BLOQUEO POR CONTROL REMOTO
    # ===================================================
    def mostrar_bloqueo_control_remoto(self, detectados):
        """
        Bloquea el inicio del examen si se detecta software de control remoto activo.
        """
        self.bloqueo_seguridad_activo = True
        frame = self.limpiar_frame()
        frame.configure(bg=COLORS['bg_white'])

        cont = tk.Frame(frame, bg=COLORS['bg_white'])
        cont.place(relx=0.5, rely=0.5, anchor='center')

        # Icono (estilo consistente con el bloqueo por pantallas)
        c = tk.Canvas(cont, width=180, height=140, bg=COLORS['bg_white'], highlightthickness=0)
        c.pack(pady=(0, 10))
        c.create_rectangle(35, 25, 145, 90, outline='#111827', width=3, fill='#f3f4f6')
        c.create_rectangle(70, 92, 110, 100, outline='#111827', width=2, fill='#e5e7eb')
        c.create_rectangle(60, 100, 120, 110, outline='#111827', width=2, fill='#e5e7eb')
        c.create_oval(55, 35, 125, 105, outline=COLORS['danger'], width=6)
        c.create_line(60, 100, 120, 40, fill=COLORS['danger'], width=6)

        tk.Label(
            cont,
            text="⚠️ ACCESO BLOQUEADO",
            font=('Calibri', 18, 'bold'),
            fg=COLORS['danger'],
            bg=COLORS['bg_white']
        ).pack(pady=(0, 10))

        tk.Label(
            cont,
            text="Se detectó software de control remoto activo.\nPor seguridad, no puedes iniciar el examen.",
            font=('Calibri', 12, 'bold'),
            fg=COLORS['text_dark'],
            bg=COLORS['bg_white'],
            justify='center'
        ).pack(pady=(0, 10))

        detectados = detectados or []
        total = len(detectados)
        tiene_servicio = any(d.get('session') == 0 for d in detectados if isinstance(d, dict))

        box = tk.Frame(
            cont,
            bg='#fff5f5',
            padx=16,
            pady=12,
            highlightbackground=COLORS['danger'],
            highlightthickness=1
        )
        box.pack(fill='x', pady=(0, 12))

        tk.Label(
            box,
            text=f"Procesos detectados: {total}",
            font=('Calibri', 11, 'bold'),
            fg=COLORS['danger'],
            bg='#fff5f5'
        ).pack(anchor='w')

        # Lista compacta
        orden = sorted(
            [d for d in detectados if isinstance(d, dict)],
            key=lambda x: (str(x.get('name', '')).lower(), int(x.get('pid') or 0))
        )
        for d in orden[:10]:  # evitar UI enorme si hay muchos
            nombre = str(d.get('name') or 'Proceso')
            pid = int(d.get('pid') or 0)
            sid = d.get('session', None)
            if sid == 0:
                ses_txt = "Servicio (Session 0)"
            elif sid is None:
                ses_txt = "Sesión ?"
            else:
                ses_txt = f"Sesión {sid}"
            tk.Label(
                box,
                text=f"• {nombre} (PID {pid}) - {ses_txt}",
                font=('Calibri', 10),
                fg=COLORS['text_dark'],
                bg='#fff5f5',
                justify='left',
                anchor='w'
            ).pack(anchor='w')

        if total > 10:
            tk.Label(
                box,
                text=f"... y {total - 10} más",
                font=('Calibri', 9),
                fg=COLORS['text_muted'],
                bg='#fff5f5'
            ).pack(anchor='w', pady=(4, 0))

        msg = "Cierra AnyDesk/TeamViewer/RustDesk y vuelve a intentar."
        if tiene_servicio:
            if is_windows_admin():
                msg = (
                    "Se detectó un servicio (Session 0) activo.\n\n"
                    "La aplicación se está ejecutando como Administrador, pero el servicio no pudo ser detenido.\n\n"
                    "Solución: desinstala o desactiva el servicio de la aplicación de control remoto "
                    "y vuelve a intentar."
                )
            else:
                msg = (
                    "Se detectó un servicio (Session 0). En Windows, esto normalmente NO se puede cerrar "
                    "sin permisos de administrador.\n\n"
                    "Solución: ejecuta la aplicación como Administrador o desinstala/desactiva el servicio "
                    "antes de iniciar el examen, y vuelve a intentar."
                )
        tk.Label(
            cont,
            text=msg,
            font=('Calibri', 10),
            fg=COLORS['secondary'],
            bg=COLORS['bg_white'],
            justify='center',
            wraplength=520
        ).pack(pady=(0, 15))

        frame_btns = tk.Frame(cont, bg=COLORS['bg_white'])
        frame_btns.pack()

        tk.Button(
            frame_btns,
            text="🔄 Reintentar",
            font=('Calibri', 11, 'bold'),
            bg=COLORS['primary'],
            fg='white',
            bd=0,
            padx=22,
            pady=8,
            activebackground=COLORS['primary_dark'],
            activeforeground='white',
            cursor='hand2',
            command=self.reintentar_verificacion_control_remoto
        ).pack(side='left', padx=5)

        tk.Button(
            frame_btns,
            text="❌ Cerrar App",
            font=('Calibri', 11, 'bold'),
            bg=COLORS['danger'],
            fg='white',
            bd=0,
            padx=22,
            pady=8,
            activebackground='#b91c1c',
            activeforeground='white',
            cursor='hand2',
            command=self.salir
        ).pack(side='left', padx=5)

    def reintentar_verificacion_control_remoto(self):
        log(">>> Reintentando verificación de control remoto...")

        try:
            restantes = intentar_cerrar_control_remoto()
        except Exception:
            restantes = listar_procesos_control_remoto()

        bloqueantes = filtrar_procesos_control_remoto_bloqueantes(restantes)

        if not bloqueantes:
            log("✓ Verificación de control remoto exitosa - sin procesos detectados")
            self.bloqueo_seguridad_activo = False
            if usuario_actual:
                self.mostrar_examenes()
            else:
                self.mostrar_splash()
        else:
            log(f"✗ Control remoto sigue activo: {len(bloqueantes)} proceso(s)")
            self.mostrar_bloqueo_control_remoto(bloqueantes)

    # ===================================================
    #  V9.5: FINALIZAR EXAMEN POR DETECCIÓN DE PANTALLA
    # ===================================================
    def finalizar_examen_por_pantalla(self, num_pantallas):
        global examen_activo
        if not examen_activo:
            return
        
        examen_activo = False
        self.en_pantalla_resumen = False
        log(f">>> Finalizando examen por detección de {num_pantallas} pantallas")
        
        mostrar_taskbar()
        desbloquear_teclas()
        desinstalar_hook_teclado_ll()
        self.detener_watchdogs_examen()
        self.root.attributes('-fullscreen', False)
        self.root.attributes('-topmost', False)

        ex_id = getattr(self, "examen_id_actual", None)
        est_id = usuario_actual['id'] if usuario_actual else None

        if not ex_id or not est_id:
            self.mostrar_resultado_por_pantalla({'success': False, 'error': 'Sin datos'}, None, num_pantallas)
            return

        loading = tk.Toplevel(self.root)
        loading.title("Finalizando")
        loading.geometry("280x100")
        loading.transient(self.root)
        loading.grab_set()
        loading.configure(bg=COLORS['bg_white'])
        loading.attributes("-topmost", True)
        
        x = self.root.winfo_x() + (self.root.winfo_width() // 2) - 140
        y = self.root.winfo_y() + (self.root.winfo_height() // 2) - 50
        loading.geometry(f"+{x}+{y}")
        
        tk.Label(loading, text="⏳ Calculando nota...", font=('Calibri', 12), bg=COLORS['bg_white']).pack(pady=(25, 10))
        progress = ttk.Progressbar(loading, mode='indeterminate', length=200)
        progress.pack()
        progress.start(10)

        def pausar():
            motivo_seguridad = (
                f"Bloqueo por seguridad: se detectaron {num_pantallas} "
                f"dispositivo(s) de salida de video conectados."
            )
            # Notificar al servidor inmediatamente el evento de seguridad y guardar respuestas
            try:
                selenium_mgr.api_call('notificar_seguridad', {
                    'examen_id': ex_id,
                    'estudiante_id': est_id,
                    'tipo': 'salida_video_externa',
                    'motivo': motivo_seguridad,
                    'respuestas_json': respuestas_guardadas
                })
            except Exception as _e_notif:
                log(f"Error al notificar seguridad: {_e_notif}")

            self.root.after(0, lambda: self.mostrar_pantalla_pausa_seguridad(ex_id, est_id, num_pantallas, motivo_seguridad, loading))

        threading.Thread(target=pausar, daemon=True).start()

    def mostrar_pantalla_pausa_seguridad(self, ex_id, est_id, num_pantallas, motivo, loading=None):
        try:
            if loading:
                loading.destroy()
        except:
            pass
        
        mover_a_pantalla_principal(self.root)
        self.root.state('zoomed')

        frame = self.limpiar_frame()
        frame.configure(bg=COLORS['bg_light'])

        cont = tk.Frame(frame, bg=COLORS['bg_white'], padx=40, pady=35,
                        highlightbackground=COLORS['border'], highlightthickness=1)
        cont.place(relx=0.5, rely=0.5, anchor='center')

        es_pausa_docente = ('docente' in str(motivo).lower()) or (num_pantallas <= 1)

        # Encabezado con Icono
        icon_txt = "⏸️" if es_pausa_docente else "⚠️"
        titulo_txt = "EXAMEN PAUSADO POR EL DOCENTE" if es_pausa_docente else "EXAMEN PAUSADO POR SEGURIDAD"
        titulo_fg = '#d97706' if es_pausa_docente else '#dc2626'

        tk.Label(cont, text=icon_txt, font=('Segoe UI', 38), bg=COLORS['bg_white']).pack(pady=(0, 5))
        tk.Label(cont, text=titulo_txt, font=('Segoe UI', 18, 'bold'),
                 fg=titulo_fg, bg=COLORS['bg_white']).pack(pady=(0, 6))

        if es_pausa_docente:
            sub_txt = "El docente a cargo ha colocado temporalmente tu examen en pausa."
        else:
            sub_txt = f"Se detectó un imprevisto / salida de video externa ({num_pantallas} pantallas)."
        tk.Label(cont, text=sub_txt, font=('Segoe UI', 11),
                 fg=COLORS['text_dark'], bg=COLORS['bg_white'], justify='center').pack(pady=(0, 12))

        # Cuadro de progreso y estado de respuestas
        pag_actual = getattr(self, 'pagina_actual', 1)
        resp_guardadas_len = len(globals().get('respuestas_guardadas', {}))
        
        info_prog_frame = tk.Frame(cont, bg='#f0fdf4', padx=18, pady=12,
                                   highlightbackground='#86efac', highlightthickness=1)
        info_prog_frame.pack(fill='x', pady=(0, 14))
        
        tk.Label(info_prog_frame, text=f"📍 Te has quedado en la Pregunta #{pag_actual}",
                 font=('Segoe UI', 11, 'bold'), fg='#15803d', bg='#f0fdf4').pack(anchor='w')
        tk.Label(info_prog_frame, text=f"Tus {resp_guardadas_len} respuestas guardadas están completamente aseguradas en el sistema.",
                 font=('Segoe UI', 9.5), fg='#166534', bg='#f0fdf4').pack(anchor='w', pady=(2, 0))

        if es_pausa_docente:
            msg_instrucciones = (
                "• Por favor permanece atento en esta pantalla.\n"
                "• Cuando tu docente reanude la sesión desde su panel de control,\n"
                "  tu evaluación continuará automáticamente en la Pregunta #" + str(pag_actual) + ".\n"
                "• No perderás tiempo ni tus respuestas guardadas."
            )
        else:
            msg_instrucciones = (
                "Para poder continuar con tu examen:\n"
                "1. Desconecta de inmediato cualquier pantalla externa o cable HDMI / VGA.\n"
                "2. Comunícate con tu docente para que autorice y reanude tu examen.\n"
                "3. Cuando el docente lo reanude, el examen continuará automáticamente."
            )

        tk.Label(cont, text=msg_instrucciones, font=('Segoe UI', 10),
                 fg=COLORS['text_dark'], bg='#fffbeb', padx=20, pady=12, justify='left',
                 highlightbackground='#fde68a', highlightthickness=1).pack(fill='x', pady=(0, 14))

        lbl_estado_check = tk.Label(cont, text="⏳ Esperando que el docente reanude el examen...",
                                    font=('Segoe UI', 10, 'italic'),
                                    fg='#b45309', bg=COLORS['bg_white'])
        lbl_estado_check.pack(pady=(0, 14))

        frame_btns = tk.Frame(cont, bg=COLORS['bg_white'])
        frame_btns.pack()

        self.pausa_seguridad_activa = True

        def verificar_reanudacion():
            if not getattr(self, "pausa_seguridad_activa", False):
                return
            
            # Si no es pausa del docente y aún hay pantallas múltiples
            if not es_pausa_docente:
                hay_m, n_actual = hay_multiples_pantallas()
                if hay_m:
                    lbl_estado_check.config(
                        text=f"❌ Aún se detectan {n_actual} pantallas. Desconecta los monitores externos primero.",
                        fg=COLORS['danger']
                    )
                    return

            lbl_estado_check.config(text="🔄 Consultando estado de reanudación con el servidor...", fg=COLORS['primary'])
            
            def consultar_api():
                try:
                    res = selenium_mgr.api_call('verificar_tiempo', {
                        'examen_id': ex_id,
                        'estudiante_id': est_id
                    })
                    if res.get('success'):
                        if res.get('pausado'):
                            lbl_estado_check.config(
                                text="⏳ El docente aún no ha reanudado tu examen. Esperando autorización...",
                                fg='#b45309'
                            )
                        else:
                            self.pausa_seguridad_activa = False
                            lbl_estado_check.config(
                                text="✅ ¡El docente ha reanudado tu examen! Continuando...",
                                fg=COLORS['success']
                            )
                            # Continuar el examen sin perder respuestas
                            self.root.after(800, lambda: self.iniciar_examen({'id': ex_id}, None))
                    else:
                        lbl_estado_check.config(text=f"Aviso: {res.get('error', 'Sin respuesta')}", fg=COLORS['danger'])
                except Exception as ex_err:
                    lbl_estado_check.config(text=f"Aviso de red: {ex_err}", fg=COLORS['danger'])

            threading.Thread(target=consultar_api, daemon=True).start()

        btn_verificar = tk.Button(
            frame_btns,
            text="  🔄 Comprobar Reanudación Ahora  ",
            font=('Segoe UI', 10, 'bold'),
            bg=COLORS['primary'],
            fg='white',
            bd=0,
            padx=18,
            pady=8,
            activebackground=COLORS['primary_dark'],
            activeforeground='white',
            cursor='hand2',
            command=verificar_reanudacion
        )
        btn_verificar.pack(side='left', padx=6)

        btn_salir = tk.Button(
            frame_btns,
            text="  🚪 Salir a Mis Exámenes  ",
            font=('Segoe UI', 10),
            bg='#f1f5f9',
            fg=COLORS['text_dark'],
            bd=0,
            padx=18,
            pady=8,
            activebackground='#e2e8f0',
            activeforeground=COLORS['text_dark'],
            cursor='hand2',
            command=self.mostrar_examenes
        )
        btn_salir.pack(side='left', padx=6)

        def auto_check_loop():
            if getattr(self, "pausa_seguridad_activa", False):
                verificar_reanudacion()
                self.root.after(3000, auto_check_loop)

        self.root.after(3000, auto_check_loop)

    # ===================================================
    #  SEGURIDAD: FORZAR PRIMER PLANO
    # ===================================================
    def forzar_primer_plano(self):
        if not examen_activo:
            return

        if self.dialogo_activo:
            self.root.after(500, self.forzar_primer_plano)
            return

        # No forzar primer plano si la ventana de zoom está abierta
        if self.zoom_window and self.zoom_window.winfo_exists():
            self.root.after(500, self.forzar_primer_plano)
            return

        try:
            ocultar_taskbar()
            cerrar_apps_no_permitidas()
            cerrar_navegadores_nuevos()
            mover_a_pantalla_principal(self.root)

            focus_widget = None
            try:
                focus_widget = self.root.focus_get()
            except Exception:
                focus_widget = None

            self.root.lift()
            self.root.attributes("-topmost", True)
            self.root.focus_force()
            if focus_widget and focus_widget.winfo_exists():
                focus_widget.focus_set()
        except Exception as e:
            log(f"kiosko error: {e}")

        self.root.after(500, self.forzar_primer_plano)

    # ===============================================
    #  SPLASH - ESTILO MOODLE
    # ===============================================
    def mostrar_splash(self):
        self.bloqueo_seguridad_activo = False
        self.finalizando_por_pantalla = False
        
        frame = self.limpiar_frame()
        frame.configure(bg=COLORS['bg_white'])

        container = tk.Frame(frame, bg=COLORS['bg_white'])
        container.place(relx=0.5, rely=0.5, anchor='center')

        # Logo UG en splash (recurso local compatible con .exe/PyInstaller)
        logo_path = resource_path(os.path.join("Imagenes", "LogoUGcolor.png"))
        if os.path.exists(logo_path):
            try:
                if PIL_AVAILABLE:
                    img = Image.open(logo_path)
                    img = img.resize((160, 110), Image.LANCZOS)
                    self.splash_logo_img = ImageTk.PhotoImage(img)
                else:
                    # Fallback sin PIL (Tk 8.6+ soporta PNG)
                    self.splash_logo_img = tk.PhotoImage(file=logo_path)
                tk.Label(container, image=self.splash_logo_img, bg=COLORS['bg_white']).pack(pady=(0, 10))
            except Exception as e:
                log(f"No se pudo cargar logo splash: {e}")
                logo_frame = tk.Frame(container, bg=COLORS['primary'], width=80, height=80)
                logo_frame.pack(pady=(0, 15))
                logo_frame.pack_propagate(False)
                tk.Label(logo_frame, text="📋", font=('Calibri', 36), bg=COLORS['primary'], fg='white').place(relx=0.5, rely=0.5, anchor='center')
        else:
            logo_frame = tk.Frame(container, bg=COLORS['primary'], width=80, height=80)
            logo_frame.pack(pady=(0, 15))
            logo_frame.pack_propagate(False)
            tk.Label(logo_frame, text="📋", font=('Calibri', 36), bg=COLORS['primary'], fg='white').place(relx=0.5, rely=0.5, anchor='center')

        tk.Label(container, text=APP_NAME, font=('Calibri', 20, 'bold'),
                 bg=COLORS['bg_white'], fg=COLORS['primary']).pack(pady=(0, 5))
        tk.Label(container, text="Versión 9.6", font=('Calibri', 10),
                 bg=COLORS['bg_white'], fg=COLORS['text_muted']).pack(pady=(0, 25))

        self.lbl_splash_estado = tk.Label(
            container,
            text="Conectando al servidor...",
            font=('Calibri', 11),
            bg=COLORS['bg_white'],
            fg=COLORS['secondary'],
            wraplength=320,
            justify='center'
        )
        self.lbl_splash_estado.pack()

        self.progress = ttk.Progressbar(container, mode='indeterminate', length=280)
        self.progress.pack(pady=(15, 0))
        self.progress.start(10)

        def conectar():
            ok = selenium_mgr.inicializar()
            self.root.after(0, lambda: self.on_conexion(ok))

        threading.Thread(target=conectar, daemon=True).start()

    def on_conexion(self, ok):
        self.progress.stop()
        if ok:
            self.mostrar_login()
        else:
            detalle = (getattr(selenium_mgr, 'last_error', '') or '').strip()
            if detalle:
                if len(detalle) > 160:
                    detalle = detalle[:160] + "..."
                self.lbl_splash_estado.config(text=f"❌ Error de conexión al servidor\n{detalle}", fg=COLORS['danger'])
            else:
                self.lbl_splash_estado.config(text="❌ Error de conexión al servidor", fg=COLORS['danger'])

    # ===============================================
    #  LOGIN - ESTILO MOODLE
    # ===============================================
    def mostrar_login(self):
        self.bloqueo_seguridad_activo = False
        self.finalizando_por_pantalla = False
        
        frame = self.limpiar_frame()
        bg_login = '#f3f7ff'
        frame.configure(bg=bg_login)

        # Card de login centrado (estilo web UG)
        container = tk.Frame(frame, bg=COLORS['bg_white'], padx=40, pady=35,
                            highlightbackground='#dbeafe', highlightthickness=2)
        container.place(relx=0.5, rely=0.5, anchor='center')

        # Logo UG (recurso local compatible con .exe/PyInstaller)
        logo_path = resource_path(os.path.join("Imagenes", "LogoUGcolor.png"))
        if os.path.exists(logo_path):
            try:
                if PIL_AVAILABLE:
                    img = Image.open(logo_path)
                    img = img.resize((170, 120), Image.LANCZOS)
                    self.login_logo_img = ImageTk.PhotoImage(img)
                else:
                    # Fallback sin PIL (Tk 8.6+ soporta PNG)
                    self.login_logo_img = tk.PhotoImage(file=logo_path)
                tk.Label(container, image=self.login_logo_img, bg=COLORS['bg_white']).pack(pady=(0, 10))
            except Exception as e:
                log(f"No se pudo cargar logo login: {e}")
                tk.Label(container, text="UG", font=('Calibri', 26, 'bold'),
                         fg=COLORS['primary'], bg=COLORS['bg_white']).pack(pady=(0, 10))
        else:
            tk.Label(container, text="UG", font=('Calibri', 26, 'bold'),
                     fg=COLORS['primary'], bg=COLORS['bg_white']).pack(pady=(0, 10))

        tk.Label(container, text="Sistema de Examenes", font=('Calibri', 16, 'bold'),
                 bg=COLORS['bg_white'], fg=COLORS['text_dark']).pack(pady=(0, 18))

        # Campo Cedula
        self.entry_cedula = tk.Entry(container, font=('Calibri', 13), width=30,
                                    relief='flat', bg='#e9f1ff', fg=COLORS['text_dark'],
                                    highlightthickness=1, highlightbackground='#c7dbff')
        self.entry_cedula.pack(ipady=10, pady=(0, 12), fill='x')

        # Campo Contrasena
        self.entry_pass = tk.Entry(container, font=('Calibri', 13), width=30, show='•',
                                  relief='flat', bg='#e9f1ff', fg=COLORS['text_dark'],
                                  highlightthickness=1, highlightbackground='#c7dbff')
        self.entry_pass.pack(ipady=10, pady=(0, 16), fill='x')

        self.lbl_login_estado = tk.Label(container, text="", font=('Calibri', 10), bg=COLORS['bg_white'])
        self.lbl_login_estado.pack()

        self.btn_login = tk.Button(container, text="Iniciar Sesion", font=('Calibri', 12, 'bold'),
                                   bg='#0055b8', fg='white', bd=0, cursor='hand2',
                                   activebackground='#00408b', activeforeground='white',
                                   command=self.hacer_login)
        self.btn_login.pack(fill='x', ipady=11, pady=(12, 0))

        self.entry_pass.bind('<Return>', lambda e: self.hacer_login())
        self.entry_cedula.focus_set()

    def hacer_login(self):
        cedula = self.entry_cedula.get().strip()
        password = self.entry_pass.get()

        if not cedula or not password:
            self.mostrar_mensaje_error("Error", "Complete todos los campos")
            return

        self.btn_login.config(state='disabled', text='Verificando...')
        self.lbl_login_estado.config(text="🔄 Conectando...", fg=COLORS['secondary'])

        def worker():
            result = selenium_mgr.api_call('login', {'cedula': cedula, 'password': password})
            self.root.after(0, lambda: self.on_login(result))

        threading.Thread(target=worker, daemon=True).start()

    def on_login(self, result):
        global usuario_actual
        self.btn_login.config(state='normal', text='Ingresar')

        if result.get('success'):
            usuario_actual = result['usuario']
            self.lbl_login_estado.config(text="✅ Bienvenido", fg=COLORS['success'])
            self.root.after(500, self.mostrar_terminos)
        else:
            self.lbl_login_estado.config(text="❌ " + result.get('error', 'Error'), fg=COLORS['danger'])

    # ===============================================
    #  TÉRMINOS - ESTILO MOODLE
    # ===============================================
    def mostrar_terminos(self):
        self.bloqueo_seguridad_activo = False
        self.finalizando_por_pantalla = False
        
        frame = self.limpiar_frame()
        frame.configure(bg=COLORS['bg_light'])

        # Card centrado
        container = tk.Frame(frame, bg=COLORS['bg_white'], padx=70, pady=48,
                            highlightbackground=COLORS['border'], highlightthickness=1)
        container.place(relx=0.5, rely=0.5, anchor='center')

        tk.Label(container, text="⚠️  TÉRMINOS DE SEGURIDAD", font=('Calibri', 20, 'bold'),
                 fg=COLORS['danger'], bg=COLORS['bg_white']).pack()
        tk.Label(container, text=f"Estudiante: {usuario_actual['nombres']}",
                 font=('Calibri', 13, 'bold'), fg=COLORS['text_dark'], bg=COLORS['bg_white']).pack(pady=(12, 22))

        # Frame con términos
        frame_terminos = tk.Frame(container, bg='#eef2f7', padx=24, pady=18,
                                 highlightbackground=COLORS['border'], highlightthickness=1)
        frame_terminos.pack(fill='x', pady=12)

        terminos = (
            "• Solo debe usar UN monitor durante el examen.\n"
            "• Si conecta otro dispositivo de salida, el examen se finalizará\n"
            "  automáticamente con la nota de lo que haya respondido.\n"
            "• Se bloqueará la tecla Windows y combinaciones.\n"
            "• Se bloqueará Alt+Tab y Ctrl+Tab.\n"
            "• La aplicación permanecerá en primer plano.\n"
            "• Algunas aplicaciones se cerrarán automáticamente.\n"
            "• Los navegadores (excepto Chrome) serán cerrados.\n"
            "• Se intentará cerrar software de acceso remoto; si permanece activo, se bloqueará el examen.\n"
            "• Zoom permanecerá abierto para supervisión del docente."
        )
        tk.Label(frame_terminos, text=terminos, font=('Calibri', 13), bg='#eef2f7',
                 justify='left', fg='#2d3748', anchor='w').pack(fill='x')
        
        # Advertencia especial
        frame_warn = tk.Frame(container, bg='#fef2f2', padx=18, pady=12,
                             highlightbackground=COLORS['danger'], highlightthickness=1)
        frame_warn.pack(fill='x', pady=12)
        tk.Label(frame_warn, text="⚠️  IMPORTANTE",
                 font=('Calibri', 13, 'bold'), fg=COLORS['danger'], bg='#fef2f2', justify='center').pack()
        tk.Label(frame_warn, text="Si conecta un monitor/proyector adicional durante el examen,\n"
                 "este se finalizará automáticamente y se calificará con las respuestas registradas.",
                 font=('Calibri', 12, 'bold'), fg=COLORS['danger'], bg='#fef2f2', justify='center').pack(pady=(8, 0))

        self.var_acepto = tk.BooleanVar()
        tk.Checkbutton(container, text="Acepto los términos y entiendo las consecuencias", 
                       font=('Calibri', 13, 'bold'),
                       variable=self.var_acepto, bg=COLORS['bg_white'],
                       activebackground=COLORS['bg_white']).pack(pady=12)

        frame_btns = tk.Frame(container, bg=COLORS['bg_white'])
        frame_btns.pack(pady=10)

        tk.Button(frame_btns, text="➡ Continuar", font=('Calibri', 13, 'bold'),
                  bg=COLORS['primary'], fg='white', bd=0, padx=34, pady=12, cursor='hand2',
                  activebackground=COLORS['primary_dark'], activeforeground='white',
                  command=self.aceptar_terminos).pack(side='left', padx=5)

        tk.Button(frame_btns, text="✕ Salir", font=('Calibri', 13, 'bold'),
                  bg=COLORS['danger'], fg='white', bd=0, padx=34, pady=12, cursor='hand2',
                  activebackground='#b91c1c', activeforeground='white',
                  command=self.salir).pack(side='left', padx=5)

    def aceptar_terminos(self):
        if not self.var_acepto.get():
            self.mostrar_mensaje_warning("", "Debe aceptar los términos")
            return
        self.mostrar_examenes()

    # ===============================================
    #  LISTA DE EXÁMENES - ESTILO MOODLE MEJORADO
    # ===============================================
    def _formatear_fecha_examen(self, fecha_raw):
        if not fecha_raw:
            return "No definida"
        try:
            texto = str(fecha_raw).replace("T", " ").strip()
            dt = datetime.datetime.strptime(texto[:19], "%Y-%m-%d %H:%M:%S")
            return dt.strftime("%d/%m/%Y %H:%M")
        except Exception:
            return str(fecha_raw)

    def _formatear_duracion(self, minutos):
        try:
            total_min = int(minutos)
        except Exception:
            return str(minutos)
        if total_min < 60:
            return f"{total_min} min"
        horas, rem = divmod(total_min, 60)
        if rem == 0:
            return f"{horas} h"
        return f"{horas} h {rem} min"

    def _cargar_examenes_async(self):
        def cargar():
            result = selenium_mgr.api_call('examenes', {'estudiante_id': usuario_actual['id']})
            self.root.after(0, lambda: self.mostrar_lista_examenes(result))

        threading.Thread(target=cargar, daemon=True).start()

    def refrescar_examenes(self, silent=False):
        if self._cargando_examenes or not usuario_actual:
            return
        self._cargando_examenes = True
        if self.btn_refrescar_examenes and not silent:
            self.btn_refrescar_examenes.config(state='disabled')

        if not silent and hasattr(self, 'frame_lista') and self.frame_lista:
            for w in self.frame_lista.winfo_children():
                w.destroy()
            self.lbl_cargando = tk.Label(self.frame_lista, text="⏳ Cargando evaluaciones asignadas...",
                                         font=('Segoe UI', 13), fg=COLORS['text_muted'], bg=COLORS['bg_light'])
            self.lbl_cargando.pack(pady=60)

        self._cargar_examenes_async()

    def mostrar_examenes(self):
        self.bloqueo_seguridad_activo = False
        self.finalizando_por_pantalla = False
        
        frame = self.limpiar_frame()
        frame.configure(bg=COLORS['bg_light'])
        self._vista_examenes_activa = True

        # Header superior institucional de nivel universitario
        header = tk.Frame(frame, bg=COLORS['bg_white'], height=70)
        header.pack(fill='x')
        header.pack_propagate(False)

        # Franja decorativa institucional en el tope
        tk.Frame(header, bg=COLORS['primary'], height=3).pack(fill='x', side='top')

        header_inner = tk.Frame(header, bg=COLORS['bg_white'])
        header_inner.pack(fill='both', expand=True, padx=25)

        # Logo / Marca institucional UG a la izquierda
        brand_frame = tk.Frame(header_inner, bg=COLORS['bg_white'])
        brand_frame.pack(side='left', fill='y')

        tk.Label(brand_frame, text="🏛️", font=('Segoe UI', 18), bg=COLORS['bg_white']).pack(side='left', padx=(0, 10))
        brand_text_frame = tk.Frame(brand_frame, bg=COLORS['bg_white'])
        brand_text_frame.pack(side='left', fill='y', pady=10)

        tk.Label(brand_text_frame, text="UNIVERSIDAD DE GUAYAQUIL", font=('Segoe UI', 11, 'bold'),
                 fg=COLORS['primary'], bg=COLORS['bg_white'], anchor='w').pack(anchor='w')
        tk.Label(brand_text_frame, text="Sistema Seguro de Evaluación Académica", font=('Segoe UI', 8, 'bold'),
                 fg=COLORS['text_muted'], bg=COLORS['bg_white'], anchor='w').pack(anchor='w')
        
        # Info usuario y botones de control a la derecha
        user_frame = tk.Frame(header_inner, bg=COLORS['bg_white'])
        user_frame.pack(side='right', fill='y', pady=12)
        
        # Pill con nombre y rol del estudiante
        user_pill = tk.Frame(user_frame, bg='#f1f5f9', padx=12, pady=6,
                             highlightbackground=COLORS['border'], highlightthickness=1)
        user_pill.pack(side='left', padx=(0, 12))

        nom_est = usuario_actual.get('nombres', 'Estudiante')
        tk.Label(user_pill, text=f"👤 {nom_est}", font=('Segoe UI', 9, 'bold'),
                 fg=COLORS['text_dark'], bg='#f1f5f9').pack(side='left')

        role_tag = tk.Label(user_pill, text="ESTUDIANTE", font=('Segoe UI', 7, 'bold'),
                            fg='#0369a1', bg='#e0f2fe', padx=5, pady=1)
        role_tag.pack(side='left', padx=(8, 0))

        # Botón actualizar datos
        self.btn_refrescar_examenes = tk.Button(
            user_frame,
            text="  🔄 Actualizar  ",
            font=('Segoe UI', 9, 'bold'), 
            bg='#f8fafc',
            fg=COLORS['primary'],
            bd=1,
            relief='solid',
            padx=10,
            pady=5,
            cursor='hand2',
            activebackground=COLORS['primary_light'],
            activeforeground=COLORS['primary_dark'],
            command=self.refrescar_examenes
        )
        self.btn_refrescar_examenes.pack(side='left', padx=(0, 8))

        # Botón cerrar sesión
        btn_salir_app = tk.Button(
            user_frame,
            text="  🚪 Cerrar Sesión  ",
            font=('Segoe UI', 9, 'bold'), 
            bg='#fee2e2',
            fg='#dc2626',
            bd=0,
            padx=12,
            pady=6,
            cursor='hand2',
            activebackground='#fecaca',
            activeforeground='#991b1b',
            command=self.mostrar_login
        )
        btn_salir_app.pack(side='left')

        # Línea separadora sutil
        tk.Frame(frame, bg=COLORS['border'], height=1).pack(fill='x')

        # Contenido principal con padding profesional
        content = tk.Frame(frame, bg=COLORS['bg_light'])
        content.pack(fill='both', expand=True, padx=35, pady=25)

        # Banner de sección
        title_frame = tk.Frame(content, bg=COLORS['bg_light'])
        title_frame.pack(fill='x', pady=(0, 20))

        h_title = tk.Frame(title_frame, bg=COLORS['bg_light'])
        h_title.pack(fill='x')
        tk.Label(h_title, text="📋 Mis Evaluaciones Académicas", font=('Segoe UI', 18, 'bold'),
                 fg=COLORS['text_dark'], bg=COLORS['bg_light']).pack(side='left')

        tk.Label(title_frame, text="Consulta tus evaluaciones programadas, continúa exámenes en curso o revisa tus notas registradas.",
                 font=('Segoe UI', 9.5), fg=COLORS['text_muted'], bg=COLORS['bg_light']).pack(anchor='w', pady=(4, 0))

        # Frame scrollable para la lista
        self.frame_lista = tk.Frame(content, bg=COLORS['bg_light'])
        self.frame_lista.pack(fill='both', expand=True)

        self.lbl_cargando = tk.Label(self.frame_lista, text="⏳ Cargando evaluaciones asignadas...",
                                     font=('Segoe UI', 13), fg=COLORS['text_muted'], bg=COLORS['bg_light'])
        self.lbl_cargando.pack(pady=60)

        self._cargando_examenes = True
        self.btn_refrescar_examenes.config(state='disabled')
        self._cargar_examenes_async()
        self._programar_auto_refresh_examenes()

    def mostrar_lista_examenes(self, result):
        self._cargando_examenes = False
        if not self._vista_examenes_activa:
            return
        if self.btn_refrescar_examenes:
            self.btn_refrescar_examenes.config(state='normal')
        try:
            self.lbl_cargando.destroy()
        except Exception:
            pass

        if hasattr(self, 'frame_lista') and self.frame_lista:
            for w in self.frame_lista.winfo_children():
                w.destroy()

        if not result.get('success'):
            tk.Label(self.frame_lista, text=f"❌ {result.get('error', 'Error al cargar evaluaciones')}",
                     font=('Segoe UI', 12), fg=COLORS['danger'], bg=COLORS['bg_light']).pack(pady=50)
            return

        examenes_raw = result.get('examenes', [])
        examenes = []
        for ex in examenes_raw:
            nota = ex.get('nota_registrada')
            ya_rendido = False
            try:
                ya_rendido = (nota is not None and float(nota) >= 0.0)
            except (ValueError, TypeError):
                ya_rendido = False
            if ex.get('estado') == 'cerrado' and not ya_rendido:
                continue
            examenes.append(ex)
        if not examenes:
            empty_frame = tk.Frame(self.frame_lista, bg=COLORS['bg_white'], padx=45, pady=45,
                                  highlightbackground=COLORS['border'], highlightthickness=1)
            empty_frame.pack(pady=40)
            tk.Label(empty_frame, text="📭", font=('Segoe UI', 44), bg=COLORS['bg_white']).pack()
            tk.Label(empty_frame, text="No tienes evaluaciones programadas por el momento", font=('Segoe UI', 13, 'bold'),
                     fg=COLORS['text_dark'], bg=COLORS['bg_white']).pack(pady=(10, 4))
            tk.Label(empty_frame, text="Tus exámenes aparecerán aquí cuando tu docente los active en el sistema.", font=('Segoe UI', 9.5),
                     fg=COLORS['text_muted'], bg=COLORS['bg_white']).pack()
            return

        cards_grid = tk.Frame(self.frame_lista, bg=COLORS['bg_light'])
        cards_grid.pack(fill='both', expand=True)

        cards_grid.update_idletasks()
        ancho = self.frame_lista.winfo_width()
        if ancho <= 1:
            ancho = self.root.winfo_width()

        num_ex = len(examenes)
        if num_ex == 1:
            columnas = 1
            card_max_w = min(680, max(460, ancho - 80))
            cards_grid.grid_columnconfigure(0, weight=1)
            for ex in examenes:
                card = self.crear_card_examen(cards_grid, ex)
                card.pack(anchor='center', fill='x', padx=10, pady=10)
        else:
            columnas = max(1, min(3, max(1, ancho // 440)))
            for c in range(columnas):
                cards_grid.grid_columnconfigure(c, weight=1, uniform="examen_card")

            for i, ex in enumerate(examenes):
                fila = i // columnas
                col = i % columnas
                card = self.crear_card_examen(cards_grid, ex)
                card.grid(row=fila, column=col, padx=12, pady=12, sticky="nsew")

    def crear_card_examen(self, parent, ex):
        """Crea una card de examen con diseño moderno y profesional de nivel universitario."""
        nota = ex.get('nota_registrada')
        
        # Evaluar flags con seguridad numérica y de API
        en_progreso = ex.get('en_progreso')
        if en_progreso is None:
            try:
                en_progreso = (nota is not None and float(nota) == -1.0)
            except (ValueError, TypeError):
                en_progreso = False

        ya_rendido = ex.get('ya_rendido')
        if ya_rendido is None:
            try:
                ya_rendido = (nota is not None and float(nota) >= 0.0)
            except (ValueError, TypeError):
                ya_rendido = False

        esta_pausado = bool(ex.get('esta_pausado', False))
        motivo_pausa = ex.get('motivo_pausa') or ex.get('seguridad_motivo') or 'Examen pausado por el docente'
        pregunta_actual = int(ex.get('pregunta_actual') or 1)
        total_preguntas = int(ex.get('total_preguntas') or ex.get('cant_preguntas_mostrar') or 0)
        respondidas_count = int(ex.get('respondidas_count') or 0)

        es_activo = ex.get('estado') == 'activo'
        es_secuencial = bool(int(ex.get('preguntas_secuenciales', 0)))

        # Determinar colores y badge según estado real
        if en_progreso:
            if esta_pausado:
                borde = COLORS['warning']
                header_bg = '#fef3c7'
                estado_txt = "EN PAUSA"
                estado_bg = COLORS['warning']
                estado_icon = "⏸"
            else:
                borde = COLORS['info']
                header_bg = '#e0f2fe'
                estado_txt = "EN CURSO / REANUDADO"
                estado_bg = COLORS['info']
                estado_icon = "⏳"
        elif ya_rendido:
            borde = COLORS['success']
            header_bg = '#ecfdf5'
            estado_txt = "COMPLETADO"
            estado_bg = COLORS['success']
            estado_icon = "✓"
        elif es_activo:
            borde = COLORS['success']
            header_bg = '#ecfdf5'
            estado_txt = "DISPONIBLE"
            estado_bg = COLORS['success']
            estado_icon = "●"
        elif ex.get('estado') == 'cerrado':
            borde = '#94a3b8'
            header_bg = '#f1f5f9'
            estado_txt = "FINALIZADO"
            estado_bg = '#64748b'
            estado_icon = "✕"
        else:
            borde = COLORS['warning']
            header_bg = '#fffbeb'
            estado_txt = "PROGRAMADO"
            estado_bg = COLORS['warning']
            estado_icon = "◔"

        # ========= CARD PRINCIPAL CON SOMBRA Y ELEVACIÓN =========
        shadow_frame = tk.Frame(parent, bg='#e2e8f0', padx=1, pady=1)

        card = tk.Frame(shadow_frame, bg=COLORS['card_bg'], padx=0, pady=0)
        card.pack(fill='both', expand=True)

        # Barra decorativa de color superior
        color_bar = tk.Frame(card, bg=borde, height=5)
        color_bar.pack(fill='x')

        # ========= CONTENIDO PRINCIPAL =========
        content = tk.Frame(card, bg=COLORS['card_bg'], padx=22, pady=18)
        content.pack(fill='both', expand=True)

        # ========= FILA SUPERIOR: TÍTULO + BADGES =========
        top_row = tk.Frame(content, bg=COLORS['card_bg'])
        top_row.pack(fill='x', pady=(0, 12))

        # Título del examen
        title_label = tk.Label(
            top_row,
            text=ex.get('titulo', 'Evaluación'),
            font=('Segoe UI', 14, 'bold'),
            fg=COLORS['text_dark'],
            bg=COLORS['card_bg'],
            wraplength=340,
            justify='left'
        )
        title_label.pack(side='left', anchor='nw')

        # Badges alineados a la derecha
        badges_frame = tk.Frame(top_row, bg=COLORS['card_bg'])
        badges_frame.pack(side='right', anchor='ne')

        # Badge de navegación
        nav_bg = COLORS['badge_secuencial_bg'] if es_secuencial else COLORS['badge_libre_bg']
        nav_txt = "🔒 Secuencial" if es_secuencial else "🔓 Navegación Libre"

        badge_nav_frame = tk.Frame(badges_frame, bg=nav_bg, padx=8, pady=3)
        badge_nav_frame.pack(side='top', anchor='e', pady=(0, 4))
        tk.Label(
            badge_nav_frame,
            text=nav_txt,
            font=('Segoe UI', 8, 'bold'),
            fg='white',
            bg=nav_bg
        ).pack()

        # Badge de estado
        badge_estado_frame = tk.Frame(badges_frame, bg=estado_bg, padx=8, pady=3)
        badge_estado_frame.pack(side='top', anchor='e')
        tk.Label(
            badge_estado_frame,
            text=f"{estado_icon} {estado_txt}",
            font=('Segoe UI', 8, 'bold'),
            fg='white',
            bg=estado_bg
        ).pack()

        # ========= LÍNEA DIVISORIA =========
        tk.Frame(content, bg=COLORS['divider'], height=1).pack(fill='x', pady=(0, 14))

        # ========= DETALLES METADATA DEL EXAMEN =========
        meta_grid = tk.Frame(content, bg=COLORS['card_bg'])
        meta_grid.pack(fill='x', pady=(0, 10))
        meta_grid.grid_columnconfigure(0, weight=1)
        meta_grid.grid_columnconfigure(1, weight=1)

        materia = ex.get('materia', '') or "Asignatura general"
        paralelo = ex.get('paralelo') or ex.get('paralelo_codigo') or ex.get('paralelo_nombre') or "Único"
        duracion_min = ex.get('duracion_minutos', 60)
        duracion_txt = self._formatear_duracion(duracion_min)
        total_p_txt = f"{total_preguntas} preguntas" if total_preguntas > 0 else "Preguntas asignadas"

        # Item 1: Materia
        f_mat = tk.Frame(meta_grid, bg=COLORS['card_bg'])
        f_mat.grid(row=0, column=0, sticky='w', pady=3)
        tk.Label(f_mat, text="📘", font=('Segoe UI', 10), bg=COLORS['card_bg']).pack(side='left', padx=(0, 6))
        tk.Label(f_mat, text="Asignatura:", font=('Segoe UI', 9, 'bold'), fg=COLORS['text_muted'], bg=COLORS['card_bg']).pack(side='left', padx=(0, 4))
        tk.Label(f_mat, text=materia, font=('Segoe UI', 9.5, 'bold'), fg=COLORS['text_dark'], bg=COLORS['card_bg']).pack(side='left')

        # Item 2: Paralelo
        f_par = tk.Frame(meta_grid, bg=COLORS['card_bg'])
        f_par.grid(row=0, column=1, sticky='w', pady=3)
        tk.Label(f_par, text="🏷️", font=('Segoe UI', 10), bg=COLORS['card_bg']).pack(side='left', padx=(0, 6))
        tk.Label(f_par, text="Paralelo:", font=('Segoe UI', 9, 'bold'), fg=COLORS['text_muted'], bg=COLORS['card_bg']).pack(side='left', padx=(0, 4))
        tk.Label(f_par, text=paralelo, font=('Segoe UI', 9.5), fg=COLORS['text_dark'], bg=COLORS['card_bg']).pack(side='left')

        # Item 3: Duración
        f_dur = tk.Frame(meta_grid, bg=COLORS['card_bg'])
        f_dur.grid(row=1, column=0, sticky='w', pady=3)
        tk.Label(f_dur, text="⏱️", font=('Segoe UI', 10), bg=COLORS['card_bg']).pack(side='left', padx=(0, 6))
        tk.Label(f_dur, text="Duración:", font=('Segoe UI', 9, 'bold'), fg=COLORS['text_muted'], bg=COLORS['card_bg']).pack(side='left', padx=(0, 4))
        tk.Label(f_dur, text=duracion_txt, font=('Segoe UI', 9.5), fg=COLORS['text_dark'], bg=COLORS['card_bg']).pack(side='left')

        # Item 4: Cantidad Preguntas
        f_cnt = tk.Frame(meta_grid, bg=COLORS['card_bg'])
        f_cnt.grid(row=1, column=1, sticky='w', pady=3)
        tk.Label(f_cnt, text="📝", font=('Segoe UI', 10), bg=COLORS['card_bg']).pack(side='left', padx=(0, 6))
        tk.Label(f_cnt, text="Evaluación:", font=('Segoe UI', 9, 'bold'), fg=COLORS['text_muted'], bg=COLORS['card_bg']).pack(side='left', padx=(0, 4))
        tk.Label(f_cnt, text=total_p_txt, font=('Segoe UI', 9.5), fg=COLORS['text_dark'], bg=COLORS['card_bg']).pack(side='left')

        # ========= ÁREA DE ACCIONES Y CONTINUACIÓN =========
        tk.Frame(content, bg=COLORS['divider'], height=1).pack(fill='x', pady=(12, 0))
        action_frame = tk.Frame(content, bg=COLORS['card_bg'], pady=14)
        action_frame.pack(fill='x')

        if en_progreso:
            if esta_pausado:
                # Banner de examen pausado
                pause_box = tk.Frame(action_frame, bg='#fffbeb', padx=14, pady=12,
                                     highlightbackground='#fde68a', highlightthickness=1)
                pause_box.pack(fill='x', pady=(0, 10))

                h_p = tk.Frame(pause_box, bg='#fffbeb')
                h_p.pack(fill='x')
                tk.Label(h_p, text="⏸️", font=('Segoe UI', 12), bg='#fffbeb').pack(side='left')
                tk.Label(h_p, text="Examen Pausado por el Docente / Seguridad", font=('Segoe UI', 10, 'bold'),
                         fg='#b45309', bg='#fffbeb').pack(side='left', padx=(6, 0))

                msg_p = f"Detenido en la Pregunta #{pregunta_actual}"
                if total_preguntas > 0:
                    msg_p += f" de {total_preguntas}"
                msg_p += f" ({respondidas_count} respondidas guardadas).\nMotivo: {motivo_pausa}\nTu docente debe reanudar la evaluación para poder continuar."
                tk.Label(pause_box, text=msg_p, font=('Segoe UI', 9), fg='#92400e', bg='#fffbeb', justify='left').pack(anchor='w', pady=(4, 0))

                btn_chk = tk.Button(
                    action_frame,
                    text="  🔄  Comprobar si el Docente Reanudó  ",
                    font=('Segoe UI', 10, 'bold'),
                    bg=COLORS['warning'],
                    fg='white',
                    bd=0,
                    padx=18,
                    pady=9,
                    cursor='hand2',
                    activebackground='#b45309',
                    activeforeground='white',
                    command=self.refrescar_examenes
                )
                btn_chk.pack(side='right')

            else:
                # Banner de continuar examen en progreso / reanudado
                prog_box = tk.Frame(action_frame, bg='#f0fdf4', padx=14, pady=12,
                                    highlightbackground='#86efac', highlightthickness=1)
                prog_box.pack(fill='x', pady=(0, 10))

                h_pr = tk.Frame(prog_box, bg='#f0fdf4')
                h_pr.pack(fill='x')
                tk.Label(h_pr, text="📍", font=('Segoe UI', 12), bg='#f0fdf4').pack(side='left')
                tk.Label(h_pr, text="Progreso Guardado — Examen Reanudado / En Curso", font=('Segoe UI', 10, 'bold'),
                         fg='#15803d', bg='#f0fdf4').pack(side='left', padx=(6, 0))

                msg_c = f"Te has quedado en la Pregunta #{pregunta_actual}"
                if total_preguntas > 0:
                    msg_c += f" de {total_preguntas}"
                msg_c += f" ({respondidas_count} respuestas registradas).\nTodas tus respuestas anteriores se encuentran intactas en la plataforma."
                tk.Label(prog_box, text=msg_c, font=('Segoe UI', 9), fg='#166534', bg='#f0fdf4', justify='left').pack(anchor='w', pady=(4, 0))

                btn_cont = tk.Button(
                    action_frame,
                    text=f"  ▶  Continuar Examen (Pregunta #{pregunta_actual})  ",
                    font=('Segoe UI', 11, 'bold'),
                    bg=COLORS['success'],
                    fg='white',
                    bd=0,
                    padx=24,
                    pady=10,
                    cursor='hand2',
                    activebackground='#047857',
                    activeforeground='white'
                )
                btn_cont.config(command=lambda e=ex, b=btn_cont: self.iniciar_examen(e, b))
                btn_cont.pack(side='right')

        elif ya_rendido:
            # Examen completado y entregado
            mostrar_nota = normalizar_bool(ex.get('mostrar_nota'))
            if mostrar_nota:
                try:
                    n = float(nota)
                except:
                    n = 0.0
                nota_bg = '#ecfdf5' if n >= 7 else '#fef2f2'
                nota_border = '#86efac' if n >= 7 else '#fca5a5'
                nota_fg = COLORS['success'] if n >= 7 else COLORS['danger']
                nota_icon = "📈" if n >= 7 else "📊"

                nota_container = tk.Frame(action_frame, bg=nota_bg, padx=16, pady=10,
                                          highlightbackground=nota_border, highlightthickness=1)
                nota_container.pack(fill='x')
                h_n = tk.Frame(nota_container, bg=nota_bg)
                h_n.pack(fill='x')
                tk.Label(h_n, text=nota_icon, font=('Segoe UI', 12), fg=nota_fg, bg=nota_bg).pack(side='left')
                tk.Label(h_n, text="Nota final registrada", font=('Segoe UI', 10, 'bold'), fg=nota_fg, bg=nota_bg).pack(side='left', padx=(6, 0))
                tk.Label(nota_container, text=f"{n:.2f} / 10", font=('Segoe UI', 18, 'bold'), fg=nota_fg, bg=nota_bg).pack(anchor='w', pady=(4, 2))
                tk.Label(nota_container, text="Calificación sobre 10.00 puntos", font=('Segoe UI', 9), fg=COLORS['text_muted'], bg=nota_bg).pack(anchor='w')
            else:
                completado_frame = tk.Frame(action_frame, bg='#ecfdf5', padx=16, pady=12,
                                            highlightbackground='#a7f3d0', highlightthickness=1)
                completado_frame.pack(fill='x')
                c_inner = tk.Frame(completado_frame, bg='#ecfdf5')
                c_inner.pack()
                tk.Label(c_inner, text="✅", font=('Segoe UI', 12), bg='#ecfdf5').pack(side='left')
                tk.Label(c_inner, text="Examen completado y entregado", font=('Segoe UI', 11, 'bold'), fg=COLORS['success'], bg='#ecfdf5').pack(side='left', padx=(8, 0))
                tk.Label(completado_frame, text="Tus respuestas han sido enviadas satisfactoriamente al docente.", font=('Segoe UI', 9), fg=COLORS['text_muted'], bg='#ecfdf5').pack(pady=(4, 0))

        elif es_activo:
            btn_start = tk.Button(
                action_frame,
                text="  🚀  Iniciar Examen  ",
                font=('Segoe UI', 11, 'bold'),
                bg=COLORS['primary'],
                fg='white',
                bd=0,
                padx=26,
                pady=10,
                cursor='hand2',
                activebackground=COLORS['primary_dark'],
                activeforeground='white'
            )
            btn_start.config(command=lambda e=ex, b=btn_start: self.iniciar_examen(e, b))
            btn_start.pack(side='right')

        elif ex.get('estado') == 'cerrado':
            cerrado_frame = tk.Frame(action_frame, bg='#f8fafc', padx=16, pady=10,
                                     highlightbackground='#e2e8f0', highlightthickness=1)
            cerrado_frame.pack(fill='x')
            tk.Label(cerrado_frame, text="🔒 Este examen cerró su periodo de disponibilidad", font=('Segoe UI', 9.5), fg=COLORS['text_muted'], bg='#f8fafc').pack()

        return shadow_frame

    # ===============================================
    #  INICIAR EXAMEN
    # ===============================================
    def iniciar_examen(self, examen, btn=None):
        nota = examen.get('nota_registrada')
        en_progreso = examen.get('en_progreso')
        if en_progreso is None:
            try:
                en_progreso = (nota is not None and float(nota) == -1.0)
            except (ValueError, TypeError):
                en_progreso = False
        
        pregunta_act = int(examen.get('pregunta_actual') or 1)

        if en_progreso:
            msg_conf = (
                f"¿Continuar con tu examen?\n\n"
                f"Continuarás desde la Pregunta #{pregunta_act}.\n"
                f"Todas tus respuestas previas se encuentran intactas y guardadas."
            )
        else:
            msg_conf = (
                "¿Iniciar el examen?\n\n"
                "RECUERDE: Si conecta otro dispositivo de salida durante el examen,\n"
                "este se pausará por seguridad y deberá ser reanudado por el docente."
            )

        if not self.mostrar_confirmar_si_no("Confirmar Evaluación", msg_conf):
            return

        # Seguridad: si hay control remoto activo, intentamos cerrarlo sin admin.
        try:
            if btn and hasattr(btn, 'config') and btn.winfo_exists():
                btn.config(state='disabled', text='Verificando seguridad...')
        except Exception:
            pass

        try:
            restantes = intentar_cerrar_control_remoto()
        except Exception:
            restantes = listar_procesos_control_remoto()

        bloqueantes = filtrar_procesos_control_remoto_bloqueantes(restantes)
        if bloqueantes:
            self.mostrar_bloqueo_control_remoto(bloqueantes)
            return

        try:
            if btn and hasattr(btn, 'config') and btn.winfo_exists():
                btn.config(state='disabled', text='Cargando...')
        except Exception:
            pass

        self.loading = tk.Toplevel(self.root)
        self.loading.title("Preparando")
        self.loading.geometry("320x110")
        self.loading.transient(self.root)
        self.loading.grab_set()
        self.loading.configure(bg=COLORS['bg_white'])
        self.loading.attributes("-topmost", True)

        x = self.root.winfo_x() + (self.root.winfo_width() // 2) - 160
        y = self.root.winfo_y() + (self.root.winfo_height() // 2) - 55
        self.loading.geometry(f"+{x}+{y}")

        tk.Label(self.loading, text="⏳ Preparando examen...", font=('Segoe UI', 11), 
                 bg=COLORS['bg_white']).pack(pady=(25, 10))
        progress = ttk.Progressbar(self.loading, mode='indeterminate', length=220)
        progress.pack()
        progress.start(10)
        self.loading.protocol("WM_DELETE_WINDOW", lambda: None)

        def cargar_todo():
            try:
                res1 = selenium_mgr.api_call('iniciar_intento', {
                    'examen_id': examen['id'],
                    'estudiante_id': usuario_actual['id']
                })
                if not res1.get('success'):
                    if res1.get('pausado'):
                        return {'pausado': True, 'error': res1.get('error', 'Examen en pausa')}
                    return {'error': res1.get('error', 'Error al iniciar')}

                intento = res1['intento']
                puntaje = intento.get('puntaje_total')
                ya_completado = False
                if puntaje is not None and str(puntaje).strip() != '':
                    try:
                        ya_completado = (float(puntaje) >= 0.0)
                    except (ValueError, TypeError):
                        ya_completado = False

                if ya_completado:
                    return {'error': 'Ya completaste este examen'}

                res2 = selenium_mgr.api_call('examen_detalle', {
                    'examen_id': examen['id'],
                    'estudiante_id': usuario_actual['id']
                })
                if not res2.get('success'):
                    return {'error': 'No se pudo cargar el examen'}

                res3 = selenium_mgr.api_call('preguntas', {
                    'examen_id': examen['id'],
                    'estudiante_id': usuario_actual['id'],
                    'orden_ids': intento.get('orden_preguntas', '')
                })

                return {
                    'success': True,
                    'intento': intento,
                    'examen': res2['examen'],
                    'preguntas': res3.get('preguntas', [])
                }
            except Exception as e:
                log_error(e)
                return {'error': str(e)}

        def on_cargado(data):
            try:
                progress.stop()
                self.loading.destroy()
            except:
                pass

            if btn and hasattr(btn, 'config') and btn.winfo_exists():
                btn.config(state='normal')

            if data.get('pausado'):
                ex_id = examen['id']
                est_id = usuario_actual['id']
                motivo = data.get('error', 'Examen en pausa por el docente o alerta de seguridad')
                self.mostrar_pantalla_pausa_seguridad(ex_id, est_id, 1, motivo)
                return

            if 'error' in data:
                self.mostrar_mensaje_error("Error", data['error'])
                return

            global examen_actual, orden_preguntas_ids, respuestas_guardadas
            global preguntas_cargadas, tiempo_restante_global, modo_secuencial
            global pregunta_maxima_alcanzada, puntaje_por_pregunta, distribucion_puntajes

            try:
                intento = data['intento']
                self._pregunta_reanudar = int(intento.get('pregunta_actual') or 0)
                examen_actual = data['examen']
                preguntas_cargadas = data['preguntas']

                seed_base = f"{usuario_actual['id']}:{examen['id']}:{intento.get('id', '')}"
                orden_base = intento['orden_preguntas'].split(',') if intento.get('orden_preguntas') else []
                orden_preguntas_ids = mezclar_lista_deterministica(orden_base, seed_base)

                for p in preguntas_cargadas:
                    if p.get('opciones'):
                        p['opciones'] = mezclar_lista_deterministica_distinta(
                            p['opciones'],
                            f"{seed_base}:q{p.get('id')}"
                        )

                respuestas_guardadas = json.loads(intento.get('respuestas_json') or '{}')

                modo_secuencial = bool(int(examen_actual.get('preguntas_secuenciales', 0)))

                # Obtener distribución de puntajes de la API (ahora es un diccionario {id: puntaje})
                num_preguntas = len(orden_preguntas_ids)
                
                if intento.get('distribucion_puntajes'):
                    # Usar distribución de la API (diccionario {pregunta_id: puntaje})
                    distribucion_puntajes = intento.get('distribucion_puntajes')
                    # Asegurar que las claves sean strings
                    if isinstance(distribucion_puntajes, dict):
                        distribucion_puntajes = {str(k): v for k, v in distribucion_puntajes.items()}
                    log(f">>> Distribución de puntajes (dict): {distribucion_puntajes}")
                else:
                    # Crear distribución vacía si no existe
                    distribucion_puntajes = {}
                    log(f">>> Sin distribución de puntajes de la API")
                
                # Calcular puntaje promedio para referencia
                if num_preguntas > 0:
                    puntaje_por_pregunta = round(10.0 / num_preguntas, 2)
                else:
                    puntaje_por_pregunta = 1.0
                
                # Verificar que la suma sea 10
                if isinstance(distribucion_puntajes, dict):
                    suma_total = sum(distribucion_puntajes.values())
                else:
                    suma_total = 0
                log(f">>> Total preguntas: {num_preguntas}, Suma puntajes: {suma_total}")

                if modo_secuencial:
                    pregunta_maxima_alcanzada = len(respuestas_guardadas) + 1
                    pregunta_maxima_alcanzada = min(pregunta_maxima_alcanzada, len(orden_preguntas_ids))
                else:
                    pregunta_maxima_alcanzada = len(orden_preguntas_ids)

                try:
                    fecha_inicio = datetime.datetime.strptime(str(intento['fecha_inicio_real'])[:19], '%Y-%m-%d %H:%M:%S')
                except:
                    fecha_inicio = datetime.datetime.now()

                duracion = int(examen_actual.get('duracion_minutos', 60)) * 60
                hora_fin = fecha_inicio + datetime.timedelta(seconds=duracion)

                if examen_actual.get('fecha_fin'):
                    try:
                        fecha_fin_ex = datetime.datetime.strptime(str(examen_actual['fecha_fin'])[:19], '%Y-%m-%d %H:%M:%S')
                        hora_fin = min(hora_fin, fecha_fin_ex)
                    except:
                        pass

                tiempo_restante_global = max(0, int((hora_fin - datetime.datetime.now()).total_seconds()))
                self.mostrar_examen(examen['id'])

            except Exception as e:
                log_error(e)
                self.mostrar_mensaje_error("Error", f"Error: {e}")

        def worker():
            data = cargar_todo()
            self.root.after(0, lambda: on_cargado(data))

        threading.Thread(target=worker, daemon=True).start()

    # ===============================================
    #  INTERFAZ DEL EXAMEN - ESTILO MOODLE
    # ===============================================
    def _ajustar_wrap_enunciado(self, _event=None):
        if not hasattr(self, "lbl_enunciado") or not self.lbl_enunciado:
            return
        try:
            # Obtener ancho disponible del contenedor
            ancho_contenedor = self.frame_q_container.winfo_width()
            if ancho_contenedor > 100:
                ancho = max(400, int(ancho_contenedor * 0.90))
                self.lbl_enunciado.config(wraplength=min(ancho, 1100))
        except Exception:
            pass

    def _verificar_necesidad_scroll(self):
        """Verifica si el contenido necesita scroll y lo muestra/oculta."""
        try:
            self.frame_q.update_idletasks()
            contenido_alto = self.frame_q.winfo_reqheight()
            contenedor_alto = self.frame_q_container.winfo_height()
            
            if contenido_alto > contenedor_alto:
                # Mostrar scrollbar
                if not self.scrollbar_visible:
                    self.scrollbar.pack(side="right", fill="y")
                    self.scrollbar_visible = True
            else:
                # Ocultar scrollbar
                if self.scrollbar_visible:
                    self.scrollbar.pack_forget()
                    self.scrollbar_visible = False
        except:
            pass

    def _deseleccionar_respuesta(self, event):
        """Deselecciona la respuesta al hacer click en espacio vacío."""
        # Verificar que el click fue en el frame y no en un widget hijo interactivo
        widget = event.widget
        widget_class = widget.winfo_class()

        # Solo deseleccionar si el click es en Canvas, Frame o Label (no en Radiobutton)
        if widget_class in ['Canvas', 'Frame', 'Label']:
            if self.var_respuesta.get() != 0:
                self.var_respuesta.set(0)
                # Actualizar estado visual
                pid = orden_preguntas_ids[self.pagina_actual - 1] if orden_preguntas_ids else None
                if pid:
                    # Si estaba guardada en el servidor, la mantenemos visualmente pero localmente deseleccionada
                    if str(pid) not in respuestas_guardadas:
                        self.lbl_estado_preg.config(text="Sin responder aún", fg=COLORS['text_muted'])

    def _mostrar_zoom_imagen(self, imagen_data):
        """Muestra la imagen en una ventana emergente con zoom."""
        try:
            # Cerrar ventana de zoom anterior si existe
            if self.zoom_window and self.zoom_window.winfo_exists():
                self.zoom_window.destroy()

            # Crear ventana de zoom
            self.zoom_window = tk.Toplevel(self.root)
            self.zoom_window.title("Vista ampliada")
            self.zoom_window.configure(bg='#1e293b')

            # Vincular a la ventana principal y mantener siempre encima
            self.zoom_window.transient(self.root)
            self.zoom_window.attributes('-topmost', True)

            # Quitar bordes de ventana para look más limpio
            self.zoom_window.overrideredirect(False)

            # Cargar imagen en tamaño más grande
            img_zoom = cargar_imagen_base64(imagen_data, max_width=900, max_height=700)
            if not img_zoom:
                self.zoom_window.destroy()
                self.zoom_window = None
                return

            # Guardar referencia para evitar garbage collection
            self.zoom_window.img_ref = img_zoom

            # Obtener dimensiones de la pantalla
            screen_w = self.root.winfo_screenwidth()
            screen_h = self.root.winfo_screenheight()

            # Header con título y botón cerrar
            header_frame = tk.Frame(self.zoom_window, bg='#1e293b', height=45)
            header_frame.pack(fill='x')
            header_frame.pack_propagate(False)

            tk.Label(
                header_frame,
                text="📷 Vista ampliada",
                font=('Segoe UI', 11, 'bold'),
                fg='#ffffff',
                bg='#1e293b'
            ).pack(side='left', padx=15, pady=10)

            def cerrar_zoom():
                if self.zoom_window:
                    self.zoom_window.destroy()
                    self.zoom_window = None

            btn_cerrar = tk.Button(
                header_frame,
                text="✕ Cerrar",
                font=('Segoe UI', 10),
                bg='#dc2626',
                fg='#ffffff',
                bd=0,
                padx=15,
                pady=5,
                cursor='hand2',
                activebackground='#b91c1c',
                activeforeground='#ffffff',
                command=cerrar_zoom
            )
            btn_cerrar.pack(side='right', padx=15, pady=8)

            # Separador
            tk.Frame(self.zoom_window, bg='#374151', height=1).pack(fill='x')

            # Contenedor de imagen con padding
            img_container = tk.Frame(self.zoom_window, bg='#1e293b', padx=20, pady=20)
            img_container.pack(fill='both', expand=True)

            # Recuadro de la imagen
            img_frame = tk.Frame(
                img_container,
                bg='#ffffff',
                highlightbackground='#475569',
                highlightcolor='#475569',
                highlightthickness=2,
                bd=0
            )
            img_frame.pack(anchor='center')

            # Imagen
            lbl_img_zoom = tk.Label(img_frame, image=img_zoom, bg='#ffffff')
            lbl_img_zoom.pack(padx=10, pady=10)

            # Instrucciones
            tk.Label(
                self.zoom_window,
                text="Presiona ESC o haz clic en Cerrar para volver al examen",
                font=('Calibri', 9),
                fg='#94a3b8',
                bg='#1e293b'
            ).pack(pady=(0, 15))

            # Centrar ventana
            self.zoom_window.update_idletasks()
            w = self.zoom_window.winfo_width()
            h = self.zoom_window.winfo_height()
            x = (screen_w - w) // 2
            y = (screen_h - h) // 2
            self.zoom_window.geometry(f"+{x}+{y}")

            # Bind ESC para cerrar
            self.zoom_window.bind('<Escape>', lambda e: cerrar_zoom())

            # Capturar el foco y eventos del mouse
            self.zoom_window.grab_set()
            self.zoom_window.focus_set()

        except Exception as e:
            log_error(e)
            if self.zoom_window:
                try:
                    self.zoom_window.destroy()
                except:
                    pass
            self.zoom_window = None

    def toggle_timer(self):
        """Alterna la visibilidad del timer (como en Moodle)."""
        self.timer_visible = not self.timer_visible
        if self.timer_visible:
            self.timer_frame.pack(fill='x', pady=(0, 10))
            self.btn_toggle_timer.config(text="Ocultar")
        else:
            self.timer_frame.pack_forget()
            self.btn_toggle_timer.config(text="Mostrar")

    # === Modo secuencial dinámico ===
    def actualizar_badge_modo(self):
        """Refresca el badge de modo (Secuencial/Libre) en el header."""
        if not self.lbl_modo_badge:
            return
        if modo_secuencial:
            self.lbl_modo_badge.config(text="🔒 Secuencial", bg=COLORS['badge_secuencial'], fg='white')
        else:
            self.lbl_modo_badge.config(text="🔓 Libre", bg=COLORS['badge_libre'], fg='white')

    def actualizar_toggle_modo_btn(self):
        """Actualiza texto/color del botón de alternar modo."""
        if not self.btn_toggle_modo:
            return
        if modo_secuencial:
            self.btn_toggle_modo.config(text="Cambiar a libre", bg=COLORS['secondary'], activebackground=COLORS['secondary'])
        else:
            self.btn_toggle_modo.config(text="Cambiar a secuencial", bg=COLORS['primary'], activebackground=COLORS['primary_dark'])

    def set_modo_secuencial(self, value: bool):
        """Activa/desactiva el modo secuencial en caliente."""
        global modo_secuencial, pregunta_maxima_alcanzada
        value = bool(value)
        modo_secuencial = value

        if modo_secuencial:
            # En secuencial no se puede retroceder; limitar navegación al punto actual/siguiente pendiente
            pregunta_maxima_alcanzada = max(self.pagina_actual, len(respuestas_guardadas) + 1)
            pregunta_maxima_alcanzada = min(pregunta_maxima_alcanzada, len(orden_preguntas_ids))
        else:
            # Modo libre: habilitar todas
            pregunta_maxima_alcanzada = len(orden_preguntas_ids)

        self.actualizar_badge_modo()
        self.actualizar_toggle_modo_btn()
        # Re-dibujar navegación y botones según el nuevo modo
        self.crear_grid_preguntas()
        self.cargar_pregunta(self.pagina_actual)

    def toggle_modo_secuencial(self):
        """Handler del botón de alternar modo secuencial/libre."""
        self.set_modo_secuencial(not modo_secuencial)

    def _crear_panel_mensajeria(self, parent):
        panel = tk.Frame(
            parent,
            bg=COLORS['bg_white'],
            highlightbackground='#dbe3ed',
            highlightthickness=1,
            bd=0
        )
        panel.pack(fill='x', pady=(0, 12))

        tk.Frame(panel, bg=COLORS['primary'], height=3).pack(fill='x')

        header = tk.Frame(panel, bg=COLORS['bg_white'])
        header.pack(fill='x', padx=10, pady=(8, 4))

        header_text = tk.Frame(header, bg=COLORS['bg_white'])
        header_text.pack(side='left', fill='x', expand=True)

        self.lbl_mensajes_titulo = tk.Label(
            header_text,
            text="Mensajes con el docente",
            font=('Calibri', 9, 'bold'),
            fg=COLORS['text_dark'],
            bg=COLORS['bg_white']
        )
        self.lbl_mensajes_titulo.pack(anchor='w')

        tk.Label(
            header_text,
            text="Canal privado durante el examen",
            font=('Calibri', 8),
            fg=COLORS['text_muted'],
            bg=COLORS['bg_white']
        ).pack(anchor='w', pady=(1, 0))

        body = tk.Frame(panel, bg=COLORS['bg_white'])
        body.pack(fill='x', padx=10)

        chat_box = tk.Frame(
            body,
            bg='#dbe3ed',
            highlightbackground='#dbe3ed',
            highlightthickness=1
        )
        chat_box.pack(fill='x', expand=True)

        self.txt_mensajes = tk.Text(
            chat_box,
            height=9,
            wrap='word',
            font=('Calibri', 9),
            bg='#f8fafc',
            fg=COLORS['text_dark'],
            bd=0,
            relief='flat',
            padx=8,
            pady=8,
            spacing1=2,
            spacing3=4,
            insertbackground=COLORS['text_dark']
        )
        self.txt_mensajes.config(state='disabled')
        self.txt_mensajes.tag_configure(
            'msg_chip_doc',
            font=('Calibri', 8, 'bold'),
            foreground='#0c4a6e',
            background='#e0f2fe'
        )
        self.txt_mensajes.tag_configure(
            'msg_chip_me',
            font=('Calibri', 8, 'bold'),
            foreground='#065f46',
            background='#dcfce7'
        )
        self.txt_mensajes.tag_configure(
            'msg_time',
            font=('Calibri', 8),
            foreground=COLORS['text_muted']
        )
        self.txt_mensajes.tag_configure(
            'msg_text_doc',
            font=('Calibri', 9),
            foreground='#1f2937',
            lmargin1=2,
            lmargin2=2,
            spacing3=6
        )
        self.txt_mensajes.tag_configure(
            'msg_text_me',
            font=('Calibri', 9),
            foreground='#0f172a',
            lmargin1=2,
            lmargin2=2,
            spacing3=6
        )
        self.txt_mensajes.tag_configure(
            'msg_divider',
            foreground='#cbd5e1'
        )
        self.txt_mensajes.tag_configure(
            'msg_empty',
            font=('Calibri', 9, 'italic'),
            foreground=COLORS['text_muted']
        )

        scrollbar_style = 'Chat.Vertical.TScrollbar'
        try:
            style = ttk.Style()
            style.configure(
                scrollbar_style,
                troughcolor='#eef2f7',
                background='#c1ccda',
                bordercolor='#eef2f7',
                arrowcolor='#64748b',
                relief='flat'
            )
        except Exception:
            scrollbar_style = 'Vertical.TScrollbar'

        scroll = ttk.Scrollbar(chat_box, orient='vertical', style=scrollbar_style, command=self.txt_mensajes.yview)
        self.txt_mensajes.configure(yscrollcommand=scroll.set)
        self.txt_mensajes.pack(side='left', fill='both', expand=True)
        scroll.pack(side='right', fill='y')

        input_frame = tk.Frame(panel, bg=COLORS['bg_white'])
        input_frame.pack(fill='x', padx=10, pady=(7, 8))

        entry_wrap = tk.Frame(
            input_frame,
            bg='#cfd8e3',
            highlightbackground='#cfd8e3',
            highlightthickness=1
        )
        entry_wrap.pack(side='left', fill='x', expand=True)

        self.entry_mensaje = tk.Entry(
            entry_wrap,
            font=('Calibri', 9),
            bg=COLORS['bg_white'],
            fg=COLORS['text_dark'],
            bd=0,
            relief='flat',
            insertbackground=COLORS['text_dark']
        )
        self.entry_mensaje.pack(fill='x', padx=9, pady=7)
        self.entry_mensaje.bind('<Return>', lambda e: self.enviar_mensaje_docente())

        # Placeholder visual (solo texto, no se envia como mensaje)
        self._placeholder_mensaje_txt = "Enviar mensaje a docente"
        self._placeholder_mensaje_activo = False

        def _set_entry_focus(active):
            color = COLORS['primary'] if active else '#cfd8e3'
            try:
                entry_wrap.config(bg=color, highlightbackground=color)
            except Exception:
                pass

        def _set_placeholder():
            if not self.entry_mensaje or not self.entry_mensaje.winfo_exists():
                return
            if self.entry_mensaje.get():
                return
            self.entry_mensaje.insert(0, self._placeholder_mensaje_txt)
            try:
                self.entry_mensaje.config(fg=COLORS['text_muted'])
            except Exception:
                pass
            self._placeholder_mensaje_activo = True

        def _clear_placeholder():
            if not self.entry_mensaje or not self.entry_mensaje.winfo_exists():
                return
            if not getattr(self, '_placeholder_mensaje_activo', False):
                return
            self.entry_mensaje.delete(0, 'end')
            try:
                self.entry_mensaje.config(fg=COLORS['text_dark'])
            except Exception:
                pass
            self._placeholder_mensaje_activo = False

        self.entry_mensaje.bind('<FocusIn>', lambda e: (_set_entry_focus(True), _clear_placeholder()))
        self.entry_mensaje.bind('<FocusOut>', lambda e: (_set_entry_focus(False), _set_placeholder() if not self.entry_mensaje.get().strip() else None))
        _set_placeholder()

        self.btn_enviar_mensaje = tk.Button(
            input_frame,
            text="Enviar",
            font=('Calibri', 9, 'bold'),
            bg=COLORS['primary'],
            fg='white',
            bd=0,
            padx=14,
            pady=7,
            cursor='hand2',
            activebackground=COLORS['primary_dark'],
            activeforeground='white',
            relief='flat',
            command=self.enviar_mensaje_docente
        )
        self.btn_enviar_mensaje.pack(side='left', padx=(8, 0))
        self.btn_enviar_mensaje.bind('<Enter>', lambda e: self.btn_enviar_mensaje.config(bg=COLORS['primary_dark']))
        self.btn_enviar_mensaje.bind('<Leave>', lambda e: self.btn_enviar_mensaje.config(bg=COLORS['primary']))

        self.lbl_mensajes_estado = tk.Label(
            panel,
            text="",
            font=('Calibri', 8),
            fg=COLORS['text_muted'],
            bg=COLORS['bg_white'],
            anchor='w'
        )
        self.lbl_mensajes_estado.pack(fill='x', padx=10, pady=(0, 8))

    def iniciar_mensajeria(self):
        self._mensajeria_activa = True
        self._mensajes_ultimo_hash = None
        self._cargar_mensajes_una_vez()
        if self.root:
            self._mensajes_poll_id = self.root.after(MENSAJES_POLL_MS, self._poll_mensajes)

    def detener_mensajeria(self):
        self._mensajeria_activa = False
        if self._mensajes_poll_id and self.root:
            try:
                self.root.after_cancel(self._mensajes_poll_id)
            except Exception:
                pass
        self._mensajes_poll_id = None

    def _poll_mensajes(self):
        if not self._mensajeria_activa or not examen_activo:
            return
        self._cargar_mensajes_una_vez()
        if self.root:
            self._mensajes_poll_id = self.root.after(MENSAJES_POLL_MS, self._poll_mensajes)

    def _cargar_mensajes_una_vez(self):
        if not usuario_actual:
            return
        if not selenium_mgr.ready:
            return

        def worker():
            data = selenium_mgr.api_call('mensajeria_listar', {
                'examen_id': self.examen_id_actual,
                'estudiante_id': usuario_actual['id']
            })
            self.root.after(0, lambda: self._actualizar_mensajes_ui(data))

        threading.Thread(target=worker, daemon=True).start()

    def _actualizar_mensajes_ui(self, data):
        if not self.txt_mensajes or not self.txt_mensajes.winfo_exists():
            return
        focus_en_entry = False
        if self.root and self.entry_mensaje and self.entry_mensaje.winfo_exists():
            try:
                focus_en_entry = (self.root.focus_get() == self.entry_mensaje)
            except Exception:
                focus_en_entry = False
        if not data or not data.get('success'):
            if self.lbl_mensajes_estado:
                self.lbl_mensajes_estado.config(text="No se pudo cargar mensajes", fg=COLORS['danger'])
            return

        if self.lbl_mensajes_estado:
            self.lbl_mensajes_estado.config(text="", fg=COLORS['text_muted'])

        docente = data.get('docente') or {}
        if self.lbl_mensajes_titulo:
            self.lbl_mensajes_titulo.config(text="Mensajes con el docente")

        mensajes = data.get('mensajes', [])
        if MENSAJES_MAX and len(mensajes) > MENSAJES_MAX:
            mensajes = mensajes[-MENSAJES_MAX:]

        ids = "|".join(str(m.get('id', '')) for m in mensajes)
        if ids == self._mensajes_ultimo_hash:
            return
        self._mensajes_ultimo_hash = ids

        self.txt_mensajes.config(state='normal')
        self.txt_mensajes.delete('1.0', 'end')
        if not mensajes:
            self.txt_mensajes.insert('end', "No hay mensajes por ahora.\n", 'msg_empty')
        else:
            for idx, m in enumerate(mensajes):
                emisor = m.get('emisor')
                es_docente = (emisor == 'docente')
                fecha = m.get('fecha_creacion', '')
                hora = fecha[11:16] if isinstance(fecha, str) and len(fecha) >= 16 else ''
                etiqueta = " DOCENTE " if es_docente else " TU "
                tag_chip = 'msg_chip_doc' if es_docente else 'msg_chip_me'
                tag_texto = 'msg_text_doc' if es_docente else 'msg_text_me'
                texto_mensaje = str(m.get('mensaje', '') or '').strip()
                if not texto_mensaje:
                    texto_mensaje = "(Sin contenido)"

                self.txt_mensajes.insert('end', etiqueta, tag_chip)
                if hora:
                    self.txt_mensajes.insert('end', f"  {hora}\n", 'msg_time')
                else:
                    self.txt_mensajes.insert('end', "\n")
                self.txt_mensajes.insert('end', f"{texto_mensaje}\n", tag_texto)

                if idx < (len(mensajes) - 1):
                    self.txt_mensajes.insert('end', "------------------------------------------\n", 'msg_divider')

        self.txt_mensajes.config(state='disabled')
        self.txt_mensajes.see('end')
        if focus_en_entry and self.entry_mensaje:
            self.entry_mensaje.focus_set()

    def _finalizar_envio_mensaje(self, data):
        if not data or not data.get('success'):
            if self.lbl_mensajes_estado:
                self.lbl_mensajes_estado.config(text="No se pudo enviar el mensaje", fg=COLORS['danger'])
            return
        if self.lbl_mensajes_estado:
            self.lbl_mensajes_estado.config(text="", fg=COLORS['text_muted'])
        self._mensajes_ultimo_hash = None
        self._cargar_mensajes_una_vez()

    def enviar_mensaje_docente(self):
        if not self.entry_mensaje or not usuario_actual or not getattr(self, 'examen_id_actual', None):
            return
        texto = self.entry_mensaje.get().strip()
        if getattr(self, '_placeholder_mensaje_activo', False):
            return
        if not texto:
            return

        self.entry_mensaje.delete(0, 'end')
        self.entry_mensaje.focus_set()
        if self.lbl_mensajes_estado:
            self.lbl_mensajes_estado.config(text="Enviando...", fg=COLORS['primary'])

        def worker():
            data = selenium_mgr.api_call('mensajeria_enviar', {
                'examen_id': self.examen_id_actual,
                'estudiante_id': usuario_actual['id'],
                'mensaje': texto
            })
            self.root.after(0, lambda: self._finalizar_envio_mensaje(data))

        threading.Thread(target=worker, daemon=True).start()

    def limpiar_mensajes_temporales(self):
        if not usuario_actual or not getattr(self, 'examen_id_actual', None):
            return
        if not selenium_mgr.ready or not selenium_mgr.driver:
            return
        try:
            selenium_mgr.api_call('mensajeria_limpiar', {
                'estudiante_id': usuario_actual['id']
            })
        except Exception as e:
            log(f"Mensajeria limpiar error: {e}")

    def mostrar_examen(self, examen_id):
        global examen_activo, pregunta_maxima_alcanzada
        
        # Cancelar timer existente para evitar acumulación
        self.cancelar_timer()
        
        examen_activo = True
        self.finalizando_por_pantalla = False
        self.bloqueo_seguridad_activo = False
        self.en_pantalla_resumen = False
        self.timer_visible = True
        self.scrollbar_visible = True  # Control de visibilidad del scroll
        self._verificacion_tiempo_activa = True
        self._tiempo_sync_en_curso = False

        try:
            # Seguridad: cerrar control remoto justo al iniciar/retomar el examen (best-effort, sin admin).
            try:
                intentar_cerrar_control_remoto()
            except Exception:
                pass

            mover_a_pantalla_principal(self.root)
            self.root.attributes('-fullscreen', True)
            self.root.attributes('-topmost', True)

            instalar_hook_teclado_ll()
            bloquear_teclas()
            self.iniciar_watchdogs_examen()
            
            self.reiniciar_watchdog_pantallas()
            log(f">>> EXAMEN INICIADO - examen_activo={examen_activo}, watchdog de pantallas activo")

            cerrar_navegadores()
            self.forzar_primer_plano()

            frame = self.limpiar_frame()
            frame.configure(bg=COLORS['bg_light'])

            total = len(orden_preguntas_ids)
            p_guardada = 0
            try:
                if hasattr(self, '_pregunta_reanudar') and self._pregunta_reanudar:
                    p_guardada = int(self._pregunta_reanudar)
            except Exception:
                p_guardada = 0

            if p_guardada > 0:
                self.pagina_actual = min(p_guardada, max(1, total))
            elif modo_secuencial:
                self.pagina_actual = min(max(1, pregunta_maxima_alcanzada), max(1, total))
            else:
                primera_sin_responder = 1
                for idx_p, pid_val in enumerate(orden_preguntas_ids, 1):
                    if str(pid_val) not in respuestas_guardadas:
                        primera_sin_responder = idx_p
                        break
                self.pagina_actual = primera_sin_responder
            self.examen_id_actual = examen_id

            # ========= HEADER ESTILO MOODLE =========
            header = tk.Frame(frame, bg=COLORS['bg_white'], height=78)
            header.pack(fill='x')
            header.pack_propagate(False)

            header_inner = tk.Frame(header, bg=COLORS['bg_white'])
            header_inner.pack(fill='both', expand=True, padx=15)

            # Breadcrumb estilo Moodle
            titulo_examen = examen_actual.get('titulo', '')
            materia = examen_actual.get('materia', '')
            paralelo = (
                examen_actual.get('paralelo')
                or examen_actual.get('paralelo_codigo')
                or examen_actual.get('paralelo_nombre')
                or examen_actual.get('codigo_paralelo')
                or examen_actual.get('curso_paralelo')
            )
            breadcrumb = f"🏠 > {materia} > {titulo_examen}" if materia else f"🏠 > {titulo_examen}"

            header_left = tk.Frame(header_inner, bg=COLORS['bg_white'])
            header_left.pack(side='left', pady=10)
            tk.Label(
                header_left,
                text=breadcrumb,
                font=('Calibri', 10),
                fg=COLORS['secondary'],
                bg=COLORS['bg_white']
            ).pack(anchor='w')

            estudiante_nombre = ""
            try:
                estudiante_nombre = (usuario_actual or {}).get('nombres') or (usuario_actual or {}).get('nombre') or ""
            except Exception:
                estudiante_nombre = ""

            info_parts = []
            if estudiante_nombre:
                info_parts.append(f"👤 {estudiante_nombre}")
            if materia:
                info_parts.append(f"📘 {materia}")
            if paralelo:
                info_parts.append(f"🏷️ {paralelo}")
            info_line = " | ".join(info_parts)
            if info_line:
                tk.Label(
                    header_left,
                    text=info_line,
                    font=('Calibri', 9),
                    fg=COLORS['text_muted'],
                    bg=COLORS['bg_white']
                ).pack(anchor='w', pady=(2, 0))

            # Controles de modo (badge + toggle)
            header_right = tk.Frame(header_inner, bg=COLORS['bg_white'])
            header_right.pack(side='right', pady=10)
            
            self.lbl_modo_badge = tk.Label(header_right, font=('Calibri', 9, 'bold'),
                                           fg='white', padx=8, pady=2)
            self.lbl_modo_badge.pack(side='top', anchor='e', padx=5)
            # El estudiante no puede cambiar el modo; solo mostramos badge
            self.btn_toggle_modo = None
            self.actualizar_badge_modo()

            # Línea separadora
            tk.Frame(frame, bg=COLORS['border'], height=1).pack(fill='x')

            # ========= CONTENIDO PRINCIPAL =========
            main = tk.Frame(frame, bg=COLORS['bg_light'])
            main.pack(fill='both', expand=True, padx=15, pady=10)

            # ========= PANEL IZQUIERDO (Pregunta) =========
            frame_preg = tk.Frame(main, bg=COLORS['bg_light'])
            frame_preg.pack(side='left', fill='both', expand=True, padx=(0, 10))

            # Timer frame (como en Moodle)
            timer_container = tk.Frame(frame_preg, bg=COLORS['bg_light'])
            timer_container.pack(anchor='e', pady=(0, 10))

            self.timer_frame = tk.Frame(timer_container, bg=COLORS['bg_white'], 
                                        highlightbackground=COLORS['border'], highlightthickness=1)
            self.timer_frame.pack(side='left')
            
            self.lbl_timer = tk.Label(self.timer_frame, text="Tiempo restante 0:00:00", 
                                      font=('Calibri', 12), fg=COLORS['text_dark'], 
                                      bg=COLORS['bg_white'], padx=15, pady=8)
            self.lbl_timer.pack(side='left')

            self.btn_toggle_timer = tk.Button(timer_container, text="Ocultar", font=('Calibri', 9),
                                             bg='#6c757d', fg='white', bd=0, padx=10, pady=6,
                                             cursor='hand2', command=self.toggle_timer)
            self.btn_toggle_timer.pack(side='left', padx=(5, 0))

            # ========= CARD DE PREGUNTA ESTILO MOODLE =========
            self.frame_q_container = tk.Frame(frame_preg, bg=COLORS['question_bg'],
                                              highlightbackground=COLORS['border'], highlightthickness=1)
            self.frame_q_container.pack(fill='both', expand=True)

            # Scroll interno (condicional)
            self.canvas_preg = tk.Canvas(self.frame_q_container, bg=COLORS['question_bg'], highlightthickness=0)
            self.scrollbar = ttk.Scrollbar(self.frame_q_container, orient="vertical", command=self.canvas_preg.yview)
            self.frame_q = tk.Frame(self.canvas_preg, bg=COLORS['question_bg'], padx=25, pady=20)

            self.frame_q.bind("<Configure>", lambda e: self.canvas_preg.configure(scrollregion=self.canvas_preg.bbox("all")))
            self.canvas_preg.create_window((0, 0), window=self.frame_q, anchor="nw")
            self.canvas_preg.configure(yscrollcommand=self.scrollbar.set)

            self.canvas_preg.pack(side="left", fill="both", expand=True)
            # Scrollbar se muestra/oculta según necesidad
            self.scrollbar.pack(side="right", fill="y")
            
            # Bind para scroll con mouse
            def _on_mousewheel(e):
                if self.scrollbar_visible:
                    self.canvas_preg.yview_scroll(int(-1*(e.delta/120)), "units")
            self.canvas_preg.bind_all("<MouseWheel>", _on_mousewheel)
            
            # Bind para deseleccionar al hacer click en espacio vacío
            self.canvas_preg.bind("<Button-1>", self._deseleccionar_respuesta)
            self.frame_q.bind("<Button-1>", self._deseleccionar_respuesta)

            # Header de pregunta (estilo Moodle)
            preg_header = tk.Frame(self.frame_q, bg=COLORS['question_bg'], padx=15, pady=12)
            preg_header.pack(fill='x', pady=(0, 15))
            
            self.lbl_num_preg = tk.Label(preg_header, text="Pregunta 1", font=('Calibri', 12, 'bold'),
                                         fg=COLORS['text_dark'], bg=COLORS['question_bg'])
            self.lbl_num_preg.pack(side='left')

            self.lbl_estado_preg = tk.Label(preg_header, text="Sin responder aún", font=('Calibri', 9),
                                           fg=COLORS['text_muted'], bg=COLORS['question_bg'])
            self.lbl_estado_preg.pack(side='left', padx=(15, 0))
            
            # Label para puntaje de la pregunta (a la derecha)
            # El color cambiará según si tiene imagen (vale más) o no
            self.lbl_puntaje_preg = tk.Label(preg_header, text="Pts: 1.00", font=('Calibri', 10, 'bold'),
                                             fg=COLORS['primary'], bg='#e3f2fd', padx=10, pady=2)
            self.lbl_puntaje_preg.pack(side='right', padx=(10, 0))
            
            # Indicador de pregunta con imagen (mayor valor)
            self.lbl_imagen_indicator = tk.Label(preg_header, text="📷", font=('Calibri', 10),
                                                  fg=COLORS['success'], bg=COLORS['question_bg'])
            # Se mostrará solo cuando la pregunta tenga imagen

            # ========= RECUADRO ELEGANTE PARA ENUNCIADO =========
            self.frame_enunciado_container = tk.Frame(self.frame_q, bg=COLORS['question_bg'])
            self.frame_enunciado_container.pack(fill='x', pady=(0, 15), padx=5)

            # Recuadro con borde suave y fondo semi-transparente
            self.frame_enunciado_box = tk.Frame(
                self.frame_enunciado_container,
                bg=COLORS['enunciado_bg'],
                highlightbackground=COLORS['enunciado_border'],
                highlightcolor=COLORS['enunciado_border'],
                highlightthickness=2,
                bd=0
            )
            self.frame_enunciado_box.pack(fill='x', padx=2, pady=2)

            # Barra superior decorativa del enunciado
            enunciado_header = tk.Frame(self.frame_enunciado_box, bg=COLORS['primary'], height=3)
            enunciado_header.pack(fill='x')

            # Enunciado dentro del recuadro
            self.lbl_enunciado = tk.Label(
                self.frame_enunciado_box,
                text="",
                font=('Calibri', 12, 'bold'),
                fg=COLORS['text_dark'],
                bg=COLORS['enunciado_bg'],
                wraplength=680,
                justify='left',
                padx=15,
                pady=12
            )
            self.lbl_enunciado.pack(fill='x', anchor='w')

            # Frame para imagen de pregunta (centrada)
            self.frame_imagen_preg = tk.Frame(self.frame_q, bg=COLORS['question_bg'])
            self.frame_imagen_preg.pack(fill='x', pady=5)

            # Variable para almacenar imagen original para zoom
            self.imagen_original_data = None
            self.zoom_window = None

            # ========= FRAME CONTENEDOR PARA OPCIONES =========
            self.frame_opciones_container = tk.Frame(self.frame_q, bg=COLORS['question_bg'])
            self.frame_opciones_container.pack(fill='both', expand=True, pady=(10, 0))
            self.frame_opciones_container.bind("<Button-1>", self._deseleccionar_respuesta)

            # Frame para opciones (se llenará dinámicamente)
            self.frame_opciones = tk.Frame(self.frame_opciones_container, bg=COLORS['question_bg'])
            self.frame_opciones.pack(fill='both', expand=True)
            self.frame_opciones.bind("<Button-1>", self._deseleccionar_respuesta)

            self.var_respuesta = tk.IntVar(value=0)

            # ========= SIDEBAR DERECHA (Navegación) =========
            sidebar = tk.Frame(main, bg=COLORS['bg_white'], width=260,
                              highlightbackground=COLORS['border'], highlightthickness=1)
            sidebar.pack(side='right', fill='y')
            sidebar.pack_propagate(False)

            # Header sidebar
            sidebar_header = tk.Frame(sidebar, bg='#f8f9fa', height=45)
            sidebar_header.pack(fill='x')
            sidebar_header.pack_propagate(False)
            tk.Label(sidebar_header, text="Navegación por el cuestionario", font=('Calibri', 10, 'bold'),
                     fg=COLORS['text_dark'], bg='#f8f9fa').pack(pady=12, padx=10, anchor='w')

            inner_side = tk.Frame(sidebar, bg=COLORS['bg_white'], padx=15, pady=15)
            inner_side.pack(fill='both', expand=True)

            # Grid de navegación
            self.frame_grid = tk.Frame(inner_side, bg=COLORS['bg_white'])
            self.frame_grid.pack(fill='x', pady=(0, 15))

            # Panel de mensajeria (debajo del navegador)
            self._crear_panel_mensajeria(inner_side)

            # Botón terminar intento
            tk.Button(inner_side, text="Terminar intento...", font=('Calibri', 10),
                      bg=COLORS['danger'], fg='white', bd=0,
                      activebackground='#b91c1c', activeforeground='white',
                      cursor='hand2', padx=10, pady=6,
                      command=lambda: self.confirmar_fin()).pack(fill='x')

            # ========= BOTONES NAVEGACIÓN INFERIOR =========
            frame_nav = tk.Frame(frame_preg, bg=COLORS['bg_light'])
            frame_nav.pack(fill='x', pady=(10, 0))
            self.frame_nav = frame_nav

            self.btn_ant = tk.Button(frame_nav, text="← Anterior", font=('Calibri', 10, 'bold'),
                                     bg=COLORS['secondary'], fg='white', bd=0, padx=15, pady=8,
                                     cursor='hand2', state='disabled')
            self.btn_ant.pack(side='left')

            self.btn_sig = tk.Button(frame_nav, text="Siguiente →", font=('Calibri', 10, 'bold'),
                                     bg=COLORS['primary'], fg='white', bd=0, padx=15, pady=8,
                                     cursor='hand2')
            self.btn_sig.pack(side='right')

            self.crear_grid_preguntas()
            self.cargar_pregunta(self.pagina_actual)
            self.actualizar_timer()

            # Iniciar verificación periódica de tiempo y estado desde el servidor (cada 4 segundos)
            self.root.after(4000, self.verificar_tiempo_servidor)

            self.iniciar_mensajeria()

            self._ajustar_wrap_enunciado()
            self.frame_q.bind("<Configure>", self._ajustar_wrap_enunciado)
            self.canvas_preg.bind("<Configure>", self._ajustar_wrap_enunciado)

        except Exception as e:
            log_error(e)
            self.mostrar_mensaje_error("Error", f"Error construyendo interfaz: {e}")

    def crear_grid_preguntas(self):
        for w in self.frame_grid.winfo_children():
            w.destroy()

        for i, pid in enumerate(orden_preguntas_ids):
            num = i + 1
            resp = str(pid) in respuestas_guardadas
            actual = num == self.pagina_actual
            es_anterior = num < self.pagina_actual

            if modo_secuencial:
                if es_anterior:
                    # Pregunta anterior (bloqueada)
                    bg = '#d1d5db' if resp else '#fee2e2'
                    fg = '#6b7280' if resp else '#dc2626'
                    text = str(num)
                    btn = tk.Button(self.frame_grid, text=text, font=('Calibri', 9),
                                    width=3, bg=bg, fg=fg, bd=1, state='disabled',
                                    disabledforeground=fg)
                elif actual:
                    # Pregunta actual
                    btn = tk.Button(self.frame_grid, text=str(num), font=('Calibri', 9, 'bold'),
                                    width=3, bg=COLORS['bg_white'], fg=COLORS['text_dark'], bd=2,
                                    highlightbackground=COLORS['primary'], highlightthickness=2)
                else:
                    # Pregunta futura (bloqueada)
                    btn = tk.Button(self.frame_grid, text=str(num), font=('Calibri', 9),
                                    width=3, bg='#f3f4f6', fg='#9ca3af', bd=1, state='disabled',
                                    disabledforeground='#9ca3af')
            else:
                # Modo libre
                if resp:
                    bg = '#d1d5db'
                    fg = COLORS['text_dark']
                else:
                    bg = COLORS['bg_white']
                    fg = COLORS['text_dark']
                    
                bd = 2 if actual else 1
                font_style = ('Calibri', 9, 'bold') if actual else ('Calibri', 9)
                
                btn = tk.Button(self.frame_grid, text=str(num), font=font_style,
                                width=3, bg=bg, fg=fg, bd=bd, cursor='hand2',
                                command=lambda n=num: self.ir_a_pregunta(n))
                
                if actual:
                    btn.config(highlightbackground=COLORS['primary'], highlightthickness=2)

            btn.grid(row=i//5, column=i%5, padx=2, pady=2)

    def cargar_pregunta(self, num):
        global pregunta_maxima_alcanzada
        total = len(orden_preguntas_ids)
        if num < 1 or num > total:
            return

        if modo_secuencial and num < self.pagina_actual:
            self.mostrar_mensaje_warning("Modo Secuencial", "No puedes regresar a preguntas anteriores.")
            return

        self.pagina_actual = num
        if num > pregunta_maxima_alcanzada:
            pregunta_maxima_alcanzada = num

        pid = orden_preguntas_ids[num - 1]
        preg = None
        for p in preguntas_cargadas:
            if str(p['id']) == str(pid):
                preg = p
                break
        if not preg:
            return

        self.canvas_preg.yview_moveto(0)

        # Obtener puntaje de esta pregunta específica usando el ID como clave
        pregunta_id = str(preg.get('id', ''))
        
        # La distribución es un diccionario {pregunta_id: puntaje}
        if isinstance(distribucion_puntajes, dict) and pregunta_id in distribucion_puntajes:
            valor = distribucion_puntajes[pregunta_id]
        elif isinstance(distribucion_puntajes, dict) and preg.get('id') in distribucion_puntajes:
            valor = distribucion_puntajes[preg.get('id')]
        elif preg.get('valor_puntos'):
            valor = preg.get('valor_puntos')
        else:
            valor = puntaje_por_pregunta
        
        try:
            valor_float = float(valor)
            valor_txt = f"{valor_float:.2f}"
        except Exception:
            valor_txt = str(valor)

        self.lbl_num_preg.config(text=f"Pregunta {num}")
        
        # Verificar si la pregunta tiene imagen (vale más)
        tiene_imagen = bool(preg.get('imagen'))
        
        # Actualizar puntaje y colores según si tiene imagen
        if tiene_imagen:
            # Pregunta con imagen: color dorado/naranja (vale más)
            self.lbl_puntaje_preg.config(text=f"⭐ {valor_txt} pts", bg='#fef3c7', fg='#d97706')
            # Mostrar indicador de imagen
            try:
                self.lbl_imagen_indicator.pack(side='right', padx=(5, 0))
            except:
                pass
        else:
            # Pregunta sin imagen: color azul normal
            self.lbl_puntaje_preg.config(text=f"Pts: {valor_txt}", bg='#e3f2fd', fg=COLORS['primary'])
            # Ocultar indicador de imagen
            try:
                self.lbl_imagen_indicator.pack_forget()
            except:
                pass
        
        # Actualizar estado de la pregunta
        if str(pid) in respuestas_guardadas:
            self.lbl_estado_preg.config(text="Respondida", fg=COLORS['success'])
        else:
            self.lbl_estado_preg.config(text="Sin responder aún", fg=COLORS['text_muted'])

        # Enunciado en negrita (ya configurado en mostrar_examen)
        self.lbl_enunciado.config(text=preg.get('enunciado', ''))

        # Limpiar imagen anterior
        for w in self.frame_imagen_preg.winfo_children():
            w.destroy()
        self.frame_imagen_preg.pack_forget()
        self.imagen_original_data = None

        # Imagen de pregunta CENTRADA con recuadro elegante y botón zoom
        imagen_preg = preg.get('imagen')
        if imagen_preg and PIL_AVAILABLE:
            img = cargar_imagen_base64(imagen_preg, max_width=500, max_height=300)
            if img:
                self.imagenes_cache.append(img)
                self.imagen_original_data = imagen_preg  # Guardar para zoom

                # Contenedor principal de imagen
                img_container = tk.Frame(self.frame_imagen_preg, bg=COLORS['question_bg'])
                img_container.pack(anchor='center', pady=10)

                # Recuadro elegante con borde oscuro fino
                img_frame = tk.Frame(
                    img_container,
                    bg='#ffffff',
                    highlightbackground=COLORS['imagen_border'],
                    highlightcolor=COLORS['imagen_border'],
                    highlightthickness=1,
                    bd=0,
                )
                img_frame.pack(side='left')

                # Imagen dentro del recuadro
                lbl_img = tk.Label(img_frame, image=img, bg='#ffffff')
                lbl_img.pack(padx=8, pady=8)

                # Botón de zoom elegante al lado de la imagen
                zoom_frame = tk.Frame(img_container, bg=COLORS['question_bg'])
                zoom_frame.pack(side='left', padx=(8, 0), anchor='n')

                btn_zoom = tk.Button(
                    zoom_frame,
                    text="🔍",
                    font=('Segoe UI', 11),
                    bg=COLORS['zoom_btn_bg'],
                    fg=COLORS['zoom_btn_fg'],
                    bd=0,
                    padx=8,
                    pady=4,
                    cursor='hand2',
                    activebackground='#334155',
                    activeforeground='#ffffff',
                    command=lambda: self._mostrar_zoom_imagen(imagen_preg)
                )
                btn_zoom.pack()

                # Tooltip para el botón
                zoom_tip = tk.Label(
                    zoom_frame,
                    text="Zoom",
                    font=('Calibri', 8),
                    fg=COLORS['text_muted'],
                    bg=COLORS['question_bg']
                )
                zoom_tip.pack(pady=(2, 0))

                self.frame_imagen_preg.pack(fill='x', pady=5, before=self.frame_opciones_container)

        # Limpiar opciones anteriores
        for w in self.frame_opciones.winfo_children():
            w.destroy()

        # Resetear variable de respuesta
        self.var_respuesta = tk.IntVar(value=0)
        if str(pid) in respuestas_guardadas:
            self.var_respuesta.set(int(respuestas_guardadas[str(pid)]))

        letras = ['a', 'b', 'c', 'd', 'e', 'f']
        for i, op in enumerate(preg.get('opciones', [])):
            letra = letras[i] if i < len(letras) else str(i + 1)

            # ========= RECUADRO ELEGANTE PARA CADA OPCIÓN =========
            frame_op_container = tk.Frame(self.frame_opciones, bg=COLORS['question_bg'])
            frame_op_container.pack(anchor='w', fill='x', pady=5, padx=5)
            frame_op_container.bind("<Button-1>", self._deseleccionar_respuesta)

            # Recuadro con borde suave
            frame_op = tk.Frame(
                frame_op_container,
                bg=COLORS['opcion_bg'],
                highlightbackground=COLORS['opcion_border'],
                highlightcolor=COLORS['primary'],
                highlightthickness=1,
                bd=0
            )
            frame_op.pack(fill='x', padx=2, pady=1)

            # Contenido de la opción
            frame_op_inner = tk.Frame(frame_op, bg=COLORS['opcion_bg'])
            frame_op_inner.pack(fill='x', padx=12, pady=10)

            # Opciones SIN negrita (font normal)
            rb = tk.Radiobutton(
                frame_op_inner,
                text=f"{letra}. {op['contenido']}",
                variable=self.var_respuesta,
                value=int(op['id']),
                font=('Calibri', 11),
                bg=COLORS['opcion_bg'],
                anchor='w',
                activebackground=COLORS['opcion_hover'],
                selectcolor=COLORS['opcion_bg'],
                command=lambda p=pid: self.guardar_respuesta(p)
            )
            rb.pack(anchor='w')

            imagen_op = op.get('imagen')
            if imagen_op and PIL_AVAILABLE:
                img_op = cargar_imagen_base64(imagen_op, max_width=300, max_height=200)
                if img_op:
                    self.imagenes_cache.append(img_op)

                    # Contenedor de imagen de opción
                    img_op_container = tk.Frame(frame_op_inner, bg=COLORS['opcion_bg'])
                    img_op_container.pack(anchor='w', padx=(25, 0), pady=(8, 4))

                    # Imagen de opción con recuadro elegante
                    img_box = tk.Frame(
                        img_op_container,
                        bg='#ffffff',
                        highlightbackground=COLORS['imagen_border'],
                        highlightcolor=COLORS['imagen_border'],
                        highlightthickness=1,
                        bd=0,
                    )
                    img_box.pack(side='left')
                    lbl_img_op = tk.Label(img_box, image=img_op, bg='#ffffff')
                    lbl_img_op.pack(padx=6, pady=6)

                    # Botón zoom para imagen de opción
                    zoom_op_frame = tk.Frame(img_op_container, bg=COLORS['opcion_bg'])
                    zoom_op_frame.pack(side='left', padx=(6, 0), anchor='n')

                    btn_zoom_op = tk.Button(
                        zoom_op_frame,
                        text="🔍",
                        font=('Segoe UI', 9),
                        bg=COLORS['zoom_btn_bg'],
                        fg=COLORS['zoom_btn_fg'],
                        bd=0,
                        padx=6,
                        pady=2,
                        cursor='hand2',
                        activebackground='#334155',
                        activeforeground='#ffffff',
                        command=lambda img_data=imagen_op: self._mostrar_zoom_imagen(img_data)
                    )
                    btn_zoom_op.pack()

        # Configurar botones de navegación
        if modo_secuencial:
            # En modo secuencial ocultamos el botón Anterior
            try:
                self.btn_ant.pack_forget()
            except Exception:
                pass
            self.btn_ant.config(state='disabled')
            if num >= total:
                self.btn_sig.config(text="🏁 Terminar intento", bg=COLORS['danger'],
                                   state='normal', command=self.confirmar_fin)
            else:
                self.btn_sig.config(text="Siguiente →", bg=COLORS['primary'],
                                   state='normal', command=self.avanzar_secuencial)
        else:
            # Modo libre: mostrar botón Anterior
            try:
                if not self.btn_ant.winfo_ismapped():
                    self.btn_ant.pack(side='left')
            except Exception:
                pass
            self.btn_ant.config(state='normal' if num > 1 else 'disabled',
                               command=lambda: self.ir_a_pregunta(num - 1))
            if num >= total:
                self.btn_sig.config(text="🏁 Terminar intento", bg=COLORS['danger'],
                                   command=self.confirmar_fin)
            else:
                self.btn_sig.config(text="Siguiente →", bg=COLORS['primary'],
                                   command=lambda: self.ir_a_pregunta(num + 1))

        self.crear_grid_preguntas()
        self._ajustar_wrap_enunciado()
        
        # Notificar en segundo plano al servidor la pregunta actual en la que se encuentra el estudiante
        def notif_pos():
            try:
                if hasattr(self, 'examen_id_actual') and self.examen_id_actual and usuario_actual:
                    selenium_mgr.api_call('actualizar_progreso_estudiante', {
                        'examen_id': self.examen_id_actual,
                        'estudiante_id': usuario_actual['id'],
                        'pregunta_actual': num
                    })
            except Exception:
                pass
        threading.Thread(target=notif_pos, daemon=True).start()

        # Verificar si necesita scroll después de un pequeño delay para que se renderice
        self.root.after(100, self._verificar_necesidad_scroll)

    def avanzar_secuencial(self):
        global pregunta_maxima_alcanzada
        if self.pagina_actual < len(orden_preguntas_ids):
            self.pagina_actual += 1
            if self.pagina_actual > pregunta_maxima_alcanzada:
                pregunta_maxima_alcanzada = self.pagina_actual
            self.cargar_pregunta(self.pagina_actual)

    def ir_a_pregunta(self, num):
        if modo_secuencial and num < self.pagina_actual:
            return
        self.cargar_pregunta(num)

    def guardar_respuesta(self, pregunta_id):
        oid = self.var_respuesta.get()
        if oid == 0:
            # Se deseleccionó - remover de respuestas guardadas si existía
            if str(pregunta_id) in respuestas_guardadas:
                del respuestas_guardadas[str(pregunta_id)]
                self.lbl_estado_preg.config(text="Sin responder aún", fg=COLORS['text_muted'])
                self.crear_grid_preguntas()
            return
        
        respuestas_guardadas[str(pregunta_id)] = oid
        
        # Actualizar estado visual
        self.lbl_estado_preg.config(text="Respondida", fg=COLORS['success'])

        def save():
            selenium_mgr.api_call('guardar_respuesta', {
                'examen_id': self.examen_id_actual,
                'estudiante_id': usuario_actual['id'],
                'pregunta_id': pregunta_id,
                'opcion_id': oid,
                'pregunta_numero': getattr(self, 'pagina_actual', 0)
            })

        threading.Thread(target=save, daemon=True).start()
        self.crear_grid_preguntas()

    def actualizar_timer(self):
        global tiempo_restante_global, examen_activo
        if not examen_activo:
            return

        if tiempo_restante_global <= 0:
            self.auto_finalizar()
            return

        tiempo_restante_global -= 1
        h, resto = divmod(tiempo_restante_global, 3600)
        m, s = divmod(resto, 60)
        
        try:
            if not self.en_pantalla_resumen:
                tiempo_txt = f"Tiempo restante {h}:{m:02d}:{s:02d}"
                self.lbl_timer.config(text=tiempo_txt)
                if tiempo_restante_global < 300:
                    self.lbl_timer.config(fg=COLORS['danger'])
        except:
            pass

        # Guardar el ID del timer para poder cancelarlo después
        self._timer_id = self.root.after(1000, self.actualizar_timer)
    
    def cancelar_timer(self):
        """Cancela los timers actuales si existen."""
        # Cancelar timer principal
        if hasattr(self, '_timer_id') and self._timer_id:
            try:
                self.root.after_cancel(self._timer_id)
                self._timer_id = None
            except:
                pass
        # Cancelar timer de resumen
        if hasattr(self, '_timer_resumen_id') and self._timer_resumen_id:
            try:
                self.root.after_cancel(self._timer_resumen_id)
                self._timer_resumen_id = None
            except:
                pass

    def verificar_tiempo_servidor(self):
        """
        Verifica periódicamente si el tiempo del examen ha sido modificado por el docente.
        Se ejecuta cada 30 segundos mientras el examen está activo.
        
        La app consulta el servidor para obtener el tiempo restante actualizado.
        Si el tiempo cambió significativamente, se actualiza el timer local
        y se muestra una notificación al estudiante.
        """
        global tiempo_restante_global, examen_activo
        
        # Solo verificar si el examen está activo
        if not examen_activo:
            log(">>> Verificación de tiempo cancelada: examen no activo")
            return
        
        if hasattr(self, '_verificacion_tiempo_activa') and not self._verificacion_tiempo_activa:
            return
        
        if not hasattr(self, 'examen_id_actual') or not self.examen_id_actual:
            log(">>> Verificación de tiempo cancelada: sin ID de examen")
            return
        
        # No verificar si estamos en pantalla de resumen
        if hasattr(self, 'en_pantalla_resumen') and self.en_pantalla_resumen:
            # Programar próxima verificación
            if examen_activo and getattr(self, '_verificacion_tiempo_activa', True):
                self.root.after(4000, self.verificar_tiempo_servidor)
            return

        if self._tiempo_sync_en_curso:
            if examen_activo and getattr(self, '_verificacion_tiempo_activa', True):
                self.root.after(4000, self.verificar_tiempo_servidor)
            return

        self._tiempo_sync_en_curso = True
        if examen_activo and getattr(self, '_verificacion_tiempo_activa', True):
            self.root.after(4000, self.verificar_tiempo_servidor)
        
        def hacer_verificacion():
            result = None
            try:
                log(f">>> Verificando tiempo con servidor para examen {self.examen_id_actual}...")
                result = selenium_mgr.api_call('verificar_tiempo', {
                    'examen_id': self.examen_id_actual,
                    'estudiante_id': usuario_actual['id']
                })
            except Exception as e:
                log(f"Error verificando tiempo con servidor: {e}")
            finally:
                try:
                    self.root.after(0, lambda r=result: self._procesar_verificacion_tiempo(r))
                except Exception as e:
                    self._tiempo_sync_en_curso = False
                    log(f"Error programando actualizacion de tiempo: {e}")
        
        # Ejecutar en un thread separado para no bloquear la UI
        threading.Thread(target=hacer_verificacion, daemon=True).start()
    
    def _procesar_verificacion_tiempo(self, result):
        global tiempo_restante_global, examen_activo, modo_secuencial
        
        self._tiempo_sync_en_curso = False
        
        if not examen_activo or not getattr(self, '_verificacion_tiempo_activa', True):
            return
        
        if not result or not result.get('success'):
            return

        if result.get('pausado'):
            log(">>> Servidor reportó examen pausado por el docente o alerta de seguridad")
            examen_activo = False
            self._verificacion_tiempo_activa = False
            ex_id = self.examen_id_actual
            est_id = usuario_actual['id'] if (usuario_actual and 'id' in usuario_actual) else None
            motivo = result.get('motivo_pausa', 'Examen en pausa')
            self.mostrar_pantalla_pausa_seguridad(ex_id, est_id, 1, motivo)
            return
        
        # Sincronizar el modo de navegación si el docente lo cambió en tiempo real
        if 'preguntas_secuenciales' in result:
            try:
                nuevo_modo = bool(int(result.get('preguntas_secuenciales', int(modo_secuencial))))
            except Exception:
                nuevo_modo = bool(result.get('preguntas_secuenciales', modo_secuencial))
            if nuevo_modo != modo_secuencial:
                log(f">>> Modo de navegación actualizado por el docente: {'secuencial' if nuevo_modo else 'libre'}")
                self.set_modo_secuencial(nuevo_modo)
        
        nuevo_tiempo_raw = result.get('tiempo_restante', 0)
        try:
            nuevo_tiempo = int(float(nuevo_tiempo_raw))
        except (TypeError, ValueError):
            log(f">>> Tiempo restante invalido: {nuevo_tiempo_raw}")
            return
        
        if nuevo_tiempo < 0:
            nuevo_tiempo = 0
        
        diferencia = nuevo_tiempo - tiempo_restante_global
        
        if abs(diferencia) >= 2:
            if abs(diferencia) > 60:
                log(">>> TIEMPO MODIFICADO POR EL DOCENTE")
                log(f"    Tiempo anterior: {tiempo_restante_global} segundos")
                log(f"    Tiempo nuevo: {nuevo_tiempo} segundos")
                log(f"    Diferencia: {diferencia:+d} segundos ({diferencia//60:+d} minutos)")
                self.actualizar_tiempo_desde_servidor(nuevo_tiempo, diferencia)
            else:
                tiempo_restante_global = nuevo_tiempo
        
        if result.get('debe_finalizar'):
            motivo = result.get('motivo', 'El examen debe finalizar')
            log(f">>> El servidor indica finalizar: {motivo}")
            self.auto_finalizar()

    def actualizar_tiempo_desde_servidor(self, nuevo_tiempo, diferencia):
        """
        Actualiza el tiempo restante cuando el docente lo modifica.
        Muestra una notificación visual al estudiante.
        
        Args:
            nuevo_tiempo: El nuevo tiempo restante en segundos
            diferencia: La diferencia en segundos (positivo = se agregó tiempo)
        """
        global tiempo_restante_global
        
        # Actualizar la variable global
        tiempo_restante_global = nuevo_tiempo
        
        # Mostrar notificación visual al estudiante
        if diferencia > 0:
            # Se agregó tiempo
            minutos_agregados = abs(diferencia) // 60
            mensaje = f"⏰ ¡El docente agregó {minutos_agregados} minuto(s) más!"
            color_notif = '#22c55e'  # Verde
            color_texto = '#065f46'
            log(f">>> Notificando al estudiante: +{minutos_agregados} minutos")
        else:
            # Se redujo tiempo
            minutos_reducidos = abs(diferencia) // 60
            mensaje = f"⏰ El docente redujo {minutos_reducidos} minuto(s)"
            color_notif = '#f59e0b'  # Naranja/Amarillo
            color_texto = '#92400e'
            log(f">>> Notificando al estudiante: -{minutos_reducidos} minutos")
        
        # Mostrar notificación en pantalla
        self.mostrar_notificacion_tiempo(mensaje, color_notif, color_texto)
        
        # Efecto visual en el timer (si existe y es visible)
        if hasattr(self, 'lbl_timer') and self.lbl_timer.winfo_exists():
            try:
                # Cambiar color temporalmente
                self.lbl_timer.config(fg=color_notif if diferencia > 0 else '#dc2626')
                
                # Restaurar color después de 5 segundos
                def restaurar_color():
                    try:
                        if tiempo_restante_global < 300:
                            self.lbl_timer.config(fg=COLORS['danger'])
                        else:
                            self.lbl_timer.config(fg=COLORS['text_dark'])
                    except:
                        pass
                
                self.root.after(5000, restaurar_color)
            except Exception as e:
                log(f"Error actualizando visual del timer: {e}")
    
    def mostrar_notificacion_tiempo(self, mensaje, color_fondo, color_texto):
        """
        Muestra una notificación temporal flotante cuando el tiempo cambia.
        La notificación aparece en la parte superior de la pantalla y desaparece
        automáticamente después de 5 segundos.
        
        Args:
            mensaje: El mensaje a mostrar
            color_fondo: Color de fondo de la notificación
            color_texto: Color del texto
        """
        try:
            if not hasattr(self, 'frame_actual') or not self.frame_actual:
                return
            
            # Crear frame de notificación
            notif_frame = tk.Frame(
                self.frame_actual,
                bg=color_fondo,
                highlightbackground='#333',
                highlightthickness=2,
                padx=20,
                pady=12
            )
            
            # Posicionar en la parte superior central
            notif_frame.place(relx=0.5, y=80, anchor='center')
            
            # Icono y mensaje
            tk.Label(
                notif_frame,
                text=mensaje,
                font=('Calibri', 14, 'bold'),
                bg=color_fondo,
                fg=color_texto
            ).pack()
            
            # Submensaje
            tk.Label(
                notif_frame,
                text="El tiempo ha sido actualizado por el docente",
                font=('Calibri', 10),
                bg=color_fondo,
                fg=color_texto
            ).pack(pady=(5, 0))
            
            # Hacer que parpadee para llamar la atención
            def parpadear(count=0):
                if count >= 6 or not notif_frame.winfo_exists():
                    return
                try:
                    current_bg = notif_frame.cget('bg')
                    new_bg = '#fff' if current_bg == color_fondo else color_fondo
                    notif_frame.config(bg=new_bg)
                    for child in notif_frame.winfo_children():
                        child['background'] = new_bg
                    self.root.after(300, lambda: parpadear(count + 1))
                except:
                    pass
            
            parpadear()
            
            # Destruir después de 5 segundos
            def destruir_notif():
                try:
                    if notif_frame.winfo_exists():
                        notif_frame.destroy()
                except:
                    pass
            
            self.root.after(5000, destruir_notif)
            
        except Exception as e:
            log(f"Error mostrando notificación de tiempo: {e}")

    # ===================================================
    #  PANTALLA DE RESUMEN DEL INTENTO - ESTILO MOODLE
    # ===================================================
    def mostrar_resumen_intento(self):
        global examen_activo
        
        # Cancelar timer principal antes de mostrar resumen
        self.cancelar_timer()
        
        self.en_pantalla_resumen = True
        
        frame = self.limpiar_frame()
        frame.configure(bg=COLORS['bg_light'])
        
        # Header estilo examen (azul primario)
        header = tk.Frame(frame, bg=COLORS['primary'], height=90)
        header.pack(fill='x')
        header.pack_propagate(False)
        
        header_inner = tk.Frame(header, bg=COLORS['primary'])
        header_inner.place(relx=0.5, rely=0.5, anchor='center')
        
        tk.Label(header_inner, text="📋 Resumen de tu intento", 
                 font=('Calibri', 19, 'bold'), fg='white', bg=COLORS['primary']).pack()
        
        titulo_examen = examen_actual.get('titulo', 'Examen') if examen_actual else 'Examen'
        tk.Label(header_inner, text=titulo_examen, 
                 font=('Calibri', 11, 'bold'), fg='#dceafe', bg=COLORS['primary']).pack(pady=(6, 0))
        
        # Contenedor principal
        main_container = tk.Frame(frame, bg=COLORS['bg_light'])
        main_container.pack(fill='both', expand=True, padx=30, pady=20)
        
        # Card blanca
        card = tk.Frame(main_container, bg=COLORS['bg_white'], padx=26, pady=22,
                       highlightbackground=COLORS['border'], highlightthickness=1)
        card.pack(fill='both', expand=True)
        
        # Estadísticas compactas
        total = len(orden_preguntas_ids)
        respondidas = sum(1 for p in orden_preguntas_ids if str(p) in respuestas_guardadas)
        sin_responder = total - respondidas
        
        stats = tk.Frame(card, bg='#e6f7fb', padx=18, pady=14,
                         highlightbackground=COLORS['border'], highlightthickness=1)
        stats.pack(fill='x')
        
        tk.Label(stats, text=f"{respondidas} / {total}", 
                 font=('Calibri', 32, 'bold'), fg=COLORS['primary'], bg='#e6f7fb').pack()
        tk.Label(stats, text="Preguntas respondidas", 
                 font=('Calibri', 11), fg=COLORS['secondary'], bg='#e6f7fb').pack()
        
        if sin_responder > 0:
            tk.Label(stats, text=f"⚠️ Tienes {sin_responder} pregunta(s) sin responder", 
                     font=('Calibri', 11, 'bold'), fg=COLORS['danger'], bg='#e6f7fb').pack(pady=(6, 0))
        else:
            tk.Label(stats, text="✅ Has respondido todas las preguntas", 
                     font=('Calibri', 11, 'bold'), fg=COLORS['success'], bg='#e6f7fb').pack(pady=(6, 0))
        
        tk.Frame(card, bg=COLORS['border'], height=1).pack(fill='x', pady=14)
        
        # Lista con scroll y badges
        list_container = tk.Frame(card, bg=COLORS['bg_white'])
        list_container.pack(fill='both', expand=True)
        
        canvas_list = tk.Canvas(list_container, bg=COLORS['bg_white'], highlightthickness=0, height=240)
        scrollbar_list = ttk.Scrollbar(list_container, orient="vertical", command=canvas_list.yview)
        frame_list = tk.Frame(canvas_list, bg=COLORS['bg_white'])
        
        frame_list.bind("<Configure>", lambda e: canvas_list.configure(scrollregion=canvas_list.bbox("all")))
        canvas_list.create_window((0, 0), window=frame_list, anchor="nw")
        canvas_list.configure(yscrollcommand=scrollbar_list.set)
        
        canvas_list.pack(side="left", fill="both", expand=True)
        scrollbar_list.pack(side="right", fill="y")
        
        def on_mousewheel(event):
            canvas_list.yview_scroll(int(-1*(event.delta/120)), "units")
        canvas_list.bind_all("<MouseWheel>", on_mousewheel)
        
        for i, pid in enumerate(orden_preguntas_ids):
            num = i + 1
            esta_respondida = str(pid) in respuestas_guardadas
            
            row_frame = tk.Frame(frame_list, bg=COLORS['bg_white'], pady=10)
            row_frame.pack(fill='x', padx=6)
            
            tk.Label(row_frame, text=f"Pregunta {num}", 
                     font=('Calibri', 11, 'bold'), fg=COLORS['text_dark'], bg=COLORS['bg_white']).pack(side='left')
            
            if esta_respondida:
                estado = tk.Label(row_frame, text="✓ Guardada", font=('Calibri', 10, 'bold'),
                                  fg=COLORS['success'], bg='#ecfdf3', padx=10, pady=4)
                estado.pack(side='right')
            else:
                estado = tk.Label(row_frame, text="Sin responder", font=('Calibri', 10, 'bold'),
                                  fg=COLORS['danger'], bg='#fee2e2', padx=10, pady=4)
                estado.pack(side='right')
            
            tk.Frame(frame_list, bg=COLORS['divider'], height=1).pack(fill='x', padx=6)
        
        # Botones
        frame_botones = tk.Frame(card, bg=COLORS['bg_white'])
        frame_botones.pack(fill='x', pady=(18, 6))
        
        if not modo_secuencial:
            btn_volver = tk.Button(frame_botones, text="← Volver al intento (solo lectura)", 
                                   font=('Calibri', 11, 'bold'),
                                   bg=COLORS['secondary'], fg='white', bd=0, 
                                   padx=20, pady=11, cursor='hand2',
                                   activebackground='#4b5563', activeforeground='white',
                                   command=self.volver_al_examen_desde_resumen)
            btn_volver.pack(fill='x', pady=(0, 10))
        
        btn_finalizar = tk.Button(frame_botones, text="✓ Guardar intento y terminar", 
                                  font=('Calibri', 12, 'bold'),
                                  bg=COLORS['danger'], fg='white', bd=0, 
                                  padx=22, pady=12, cursor='hand2',
                                  activebackground='#b91c1c', activeforeground='white',
                                  command=self.confirmar_finalizar_desde_resumen)
        btn_finalizar.pack(fill='x')
        
        # Timer estilo examen (pill)
        frame_timer = tk.Frame(frame, bg=COLORS['bg_light'])
        frame_timer.place(relx=0.96, rely=0.05, anchor='ne')
        
        self.lbl_timer_resumen = tk.Label(frame_timer, text="⏱️ --:--", 
                                          font=('Calibri', 12, 'bold'),
                                          fg=COLORS['text_dark'], bg=COLORS['bg_white'],
                                          padx=14, pady=6,
                                          highlightbackground=COLORS['border'], highlightthickness=1)
        self.lbl_timer_resumen.pack()
        
        self.actualizar_timer_resumen()
    
    def actualizar_timer_resumen(self):
        global tiempo_restante_global, examen_activo
        if not examen_activo or not self.en_pantalla_resumen:
            return
        
        if tiempo_restante_global <= 0:
            self.auto_finalizar()
            return
        
        h, resto = divmod(tiempo_restante_global, 3600)
        m, s = divmod(resto, 60)
        try:
            if hasattr(self, 'lbl_timer_resumen') and self.lbl_timer_resumen.winfo_exists():
                self.lbl_timer_resumen.config(text=f"⏱️ {h}:{m:02d}:{s:02d}")
        except:
            pass
        
        # Usar ID para poder cancelar
        self._timer_resumen_id = self.root.after(1000, self.actualizar_timer_resumen)
    
    def volver_al_examen_desde_resumen(self):
        """Vuelve al examen desde la pantalla de resumen."""
        self.en_pantalla_resumen = False
        
        # Cancelar timers existentes para evitar acumulación
        self.cancelar_timer()
        
        # Limpiar cache de imágenes para evitar acumulación de memoria
        self.imagenes_cache = []
        
        # Desvincular eventos globales
        try:
            self.root.unbind_all("<MouseWheel>")
        except:
            pass
        
        # Forzar recolección de basura
        import gc
        gc.collect()
        
        self.mostrar_examen(self.examen_id_actual)
    
    def confirmar_finalizar_desde_resumen(self):
        total = len(orden_preguntas_ids)
        respondidas = sum(1 for p in orden_preguntas_ids if str(p) in respuestas_guardadas)
        sin_responder = total - respondidas
        
        if sin_responder > 0:
            if not self.mostrar_confirmar_si_no("Confirmar finalización", 
                f"Tienes {sin_responder} pregunta(s) sin responder.\n\n"
                "¿Estás seguro de que deseas finalizar el examen?\n"
                "Esta acción no se puede deshacer."):
                return
        else:
            if not self.mostrar_confirmar_si_no("Confirmar finalización", 
                "¿Estás seguro de que deseas finalizar el examen?\n"
                "Esta acción no se puede deshacer."):
                return
        
        self.en_pantalla_resumen = False
        self.finalizar_examen()

    def confirmar_fin(self):
        self.mostrar_resumen_intento()

    def auto_finalizar(self):
        self.en_pantalla_resumen = False
        self.mostrar_mensaje_warning("Tiempo", "⏰ El tiempo ha terminado")
        self.finalizar_examen()

    def finalizar_examen(self):
        global examen_activo
        # Marcar que el examen ya no está activo para detener verificaciones
        self._verificacion_tiempo_activa = False
        examen_activo = False
        self.en_pantalla_resumen = False
        mostrar_taskbar()
        desbloquear_teclas()
        desinstalar_hook_teclado_ll()
        self.detener_watchdogs_examen()

        loading = tk.Toplevel(self.root)
        loading.title("Finalizando")
        loading.geometry("280x90")
        loading.transient(self.root)
        loading.grab_set()
        loading.configure(bg=COLORS['bg_white'])
        loading.attributes("-topmost", True)
        tk.Label(loading, text="⏳ Finalizando examen...", font=('Calibri', 12), 
                 bg=COLORS['bg_white']).pack(pady=(25, 0))

        def fin():
            log(f">>> Finalizando examen ID={self.examen_id_actual}, estudiante={usuario_actual['id']}")
            result = selenium_mgr.api_call('finalizar_examen', {
                'examen_id': self.examen_id_actual,
                'estudiante_id': usuario_actual['id']
            })
            log(f"<<< Respuesta finalizar: {result}")
            self.root.after(0, lambda: self.mostrar_resultado(result, loading))

        threading.Thread(target=fin, daemon=True).start()

    def mostrar_resultado(self, result, loading):
        loading.destroy()
        self.root.attributes('-fullscreen', False)
        self.root.attributes('-topmost', False)
        mover_a_pantalla_principal(self.root)
        self.root.state('zoomed')
        mostrar_taskbar()

        frame = self.limpiar_frame()
        frame.configure(bg=COLORS['bg_light'])

        # Card centrado
        container = tk.Frame(frame, bg=COLORS['bg_white'], padx=50, pady=40,
                            highlightbackground=COLORS['border'], highlightthickness=1)
        container.place(relx=0.5, rely=0.5, anchor='center')

        if not result.get('success'):
            error_msg = result.get('error', 'Error desconocido al finalizar')
            log(f"ERROR al finalizar examen: {error_msg}")
            
            tk.Label(container, text="⚠️", font=('Calibri', 48), bg=COLORS['bg_white']).pack()
            tk.Label(container, text="Error al Finalizar", font=('Calibri', 20, 'bold'),
                     fg=COLORS['danger'], bg=COLORS['bg_white']).pack(pady=(10, 15))
            
            tk.Label(container, text=f"No se pudo guardar la nota:\n{error_msg}",
                     font=('Calibri', 11), fg=COLORS['secondary'], bg=COLORS['bg_white'], 
                     wraplength=400).pack(pady=10)
            
            tk.Label(container, text="Tus respuestas fueron guardadas.\nContacta al docente si el problema persiste.",
                     font=('Calibri', 10), fg=COLORS['text_muted'], bg=COLORS['bg_white']).pack(pady=5)
        else:
            tk.Label(container, text="✅", font=('Calibri', 56), bg=COLORS['bg_white']).pack()
            tk.Label(container, text="Examen Finalizado", font=('Calibri', 22, 'bold'),
                     fg=COLORS['success_dark'], bg=COLORS['bg_white']).pack(pady=(10, 20))

            mostrar_nota = normalizar_bool(result.get('mostrar_nota'))

            if mostrar_nota:
                nota = result.get('nota', 0)
                correctas = result.get('correctas', 0)
                total = result.get('total', 0)
                color = COLORS['success'] if nota >= 7 else COLORS['danger']
                bg_nota = '#f0fdf4' if nota >= 7 else '#fef2f2'
                
                nota_frame = tk.Frame(container, bg=bg_nota, padx=30, pady=20)
                nota_frame.pack(pady=10)
                
                tk.Label(nota_frame, text=f"{nota}/10", font=('Calibri', 36, 'bold'),
                         fg=color, bg=bg_nota).pack()
                
                tk.Label(nota_frame, text=f"Respuestas correctas: {correctas} de {total}",
                         font=('Calibri', 11), fg=COLORS['secondary'], bg=bg_nota).pack(pady=(5, 0))
            else:
                tk.Label(container, text="Tu examen ha sido enviado correctamente",
                         font=('Calibri', 12), fg=COLORS['secondary'], bg=COLORS['bg_white']).pack(pady=15)
            
            log(f"Examen finalizado exitosamente. Nota={result.get('nota')}, Correctas={result.get('correctas')}/{result.get('total')}")

        tk.Button(container, text="📚 Volver a mis exámenes", font=('Calibri', 11, 'bold'),
                  bg=COLORS['primary'], fg='white', bd=0, padx=25, pady=10, cursor='hand2',
                  activebackground=COLORS['primary_dark'], activeforeground='white',
                  command=self.mostrar_examenes).pack(pady=(25, 0))

# =======================================================
#  MAIN
# =======================================================
if __name__ == "__main__":
    # Windows: si corre como Administrador, activar modo estricto (también bloquea Session 0).
    # Nota: no es posible elevar sin confirmación del usuario; cuando haga falta, se usará un helper con UAC.
    if sys.platform == "win32" and is_windows_admin():
        BLOQUEAR_SERVICIOS_CONTROL_REMOTO = True

    # Verificación de VM
    vm_detected, vm_reason = detectar_vm()
    if vm_detected:
        log(f"✗ Entorno virtual detectado: {vm_reason}")
        try:
            root = tk.Tk()
            root.withdraw()
            messagebox.showerror("Entorno no permitido",
                                 "La aplicación no puede ejecutarse en máquinas virtuales.\n\nDetalle: " + vm_reason)
        except Exception as e:
            log(f"No se pudo mostrar mensaje de VM: {e}")
        sys.exit(1)

    # Verificación de Selenium / Conectividad
    if not SELENIUM_AVAILABLE:
        log("⚠ Selenium no disponible. La aplicación operará en modo directo HTTP.")

    app = App()
    app.iniciar()
