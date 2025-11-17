from argostranslate import package

def main():
    print("Actualizando índice de paquetes de Argos Translate...")
    package.update_package_index()

    print("Obteniendo lista de paquetes disponibles...")
    available_packages = package.get_available_packages()

    print("Buscando paquete de inglés (en) a español (es)...")
    en_es_packages = [p for p in available_packages if p.from_code == "en" and p.to_code == "es"]

    if not en_es_packages:
        print("❌ No se encontró ningún paquete en->es. Revisa tu conexión a internet.")
        return

    pkg = en_es_packages[0]
    print(f"Encontrado paquete: {pkg.package_name} ({pkg.from_code} -> {pkg.to_code})")
    print("Descargando modelo (puede tardar un poco)...")
    download_path = pkg.download()

    print("Instalando modelo...")
    package.install_from_path(download_path)

    print("✅ Modelo en->es instalado correctamente.")

if __name__ == "__main__":
    main()
