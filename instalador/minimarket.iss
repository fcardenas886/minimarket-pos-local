; Instalador de Minimarket POS (Inno Setup 6).
;
; Para generar el .exe (desde la raíz del proyecto):
;   php instalador/compilar.php
; Arma el paquete y compila pasando la versión de config/database.php (APP_VERSION).
; Resultado: instalador\salida\MinimarketPOS-Setup-<versión>.exe

#define AppName "Minimarket POS"
#ifndef AppVersion
  #error Compila con: php instalador/compilar.php (toma la versión de config/database.php)
#endif
#define AppPublisher "Minimarket POS"

[Setup]
AppId={{6B0E2F57-4C1A-4D7B-9E33-8A1F2C5D7E10}
AppName={#AppName}
AppVersion={#AppVersion}
AppVerName={#AppName} {#AppVersion}
AppPublisher={#AppPublisher}
DefaultDirName=C:\MinimarketPOS
DefaultGroupName={#AppName}
DisableProgramGroupPage=yes
OutputDir=salida
OutputBaseFilename=MinimarketPOS-Setup-{#AppVersion}
SetupIconFile=icono.ico
UninstallDisplayIcon={app}\icono.ico
UninstallDisplayName={#AppName}
Compression=lzma2/ultra64
SolidCompression=yes
LZMAUseSeparateProcess=yes
WizardStyle=modern
PrivilegesRequired=admin
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
CloseApplications=no

[Languages]
Name: "es"; MessagesFile: "compiler:Languages\Spanish.isl"

[Tasks]
Name: "redlocal"; Description: "Permitir tablets y celulares de la red del local (módulo Mesas y garzones)"; GroupDescription: "Acceso desde otros equipos:"; Flags: unchecked

[Files]
; Los datos, respaldos y la configuración del equipo nunca se sobrescriben al actualizar
; (app\.env y la licencia no vienen en el paquete).
Source: "paquete\*"; DestDir: "{app}"; Excludes: "datos\*,logs\*,respaldos\*,tmp\*"; Flags: recursesubdirs createallsubdirs ignoreversion

[Dirs]
Name: "{app}\datos"; Flags: uninsneveruninstall
Name: "{app}\respaldos"; Flags: uninsneveruninstall
Name: "{app}\logs"
Name: "{app}\tmp"

[Icons]
Name: "{autodesktop}\{#AppName}"; Filename: "{sys}\wscript.exe"; Parameters: """{app}\abrir.vbs"""; IconFilename: "{app}\icono.ico"; Comment: "Abrir Minimarket POS"
Name: "{group}\{#AppName}"; Filename: "{sys}\wscript.exe"; Parameters: """{app}\abrir.vbs"""; IconFilename: "{app}\icono.ico"
Name: "{group}\Respaldar ahora"; Filename: "{app}\php\php.exe"; Parameters: """{app}\app\scripts\respaldo.php"""; WorkingDir: "{app}"
Name: "{group}\Carpeta de respaldos"; Filename: "{app}\respaldos"
Name: "{group}\Desinstalar {#AppName}"; Filename: "{uninstallexe}"

[Run]
Filename: "{sys}\wscript.exe"; Parameters: """{app}\abrir.vbs"""; Description: "Abrir {#AppName}"; Flags: postinstall nowait skipifsilent

[UninstallRun]
Filename: "{app}\php\php.exe"; Parameters: """{app}\configurar.php"" --desinstalar"; WorkingDir: "{app}"; Flags: runhidden waituntilterminated; RunOnceId: "QuitarServicios"

[UninstallDelete]
Type: filesandordirs; Name: "{app}\logs"
Type: filesandordirs; Name: "{app}\tmp"
Type: files; Name: "{app}\app\.env"
Type: files; Name: "{app}\php\php.ini"
Type: files; Name: "{app}\apache\conf\httpd.conf"
Type: files; Name: "{app}\mysql\my.ini"

[Code]
// Antes de copiar archivos (actualización): detener los servicios para liberar los .exe/.dll.
function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  R: Integer;
begin
  Exec(ExpandConstant('{sys}\net.exe'), 'stop MinimarketPOS-Web', '', SW_HIDE, ewWaitUntilTerminated, R);
  Exec(ExpandConstant('{sys}\net.exe'), 'stop MinimarketPOS-DB', '', SW_HIDE, ewWaitUntilTerminated, R);
  Result := '';
end;

// Después de copiar: configurar servicios, base de datos y tareas.
procedure CurStepChanged(CurStep: TSetupStep);
var
  R: Integer;
  Red: String;
begin
  if CurStep = ssPostInstall then
  begin
    // La casilla de red se respeta en ambos sentidos al actualizar (activar o desactivar).
    if WizardIsTaskSelected('redlocal') then Red := ' --red-local' else Red := ' --solo-local';
    WizardForm.StatusLabel.Caption := 'Configurando servicios y base de datos (puede tardar un minuto)...';
    if (not Exec(ExpandConstant('{app}\php\php.exe'), '"' + ExpandConstant('{app}\configurar.php') + '" --instalar' + Red,
                 ExpandConstant('{app}'), SW_HIDE, ewWaitUntilTerminated, R)) or (R <> 0) then
      MsgBox('La configuración no terminó correctamente (código ' + IntToStr(R) + ').' + #13#10 +
             'Revisa el archivo ' + ExpandConstant('{app}\logs\instalacion.log'), mbError, MB_OK);
  end;
end;

// Al desinstalar: preguntar si se borran los datos (por defecto se conservan).
procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if CurUninstallStep = usPostUninstall then
  begin
    if MsgBox('¿Borrar también la base de datos y los respaldos?' + #13#10 +
              'Si eliges No, quedan en ' + ExpandConstant('{app}') + ' por si reinstalas.',
              mbConfirmation, MB_YESNO or MB_DEFBUTTON2) = IDYES then
    begin
      DelTree(ExpandConstant('{app}\datos'), True, True, True);
      DelTree(ExpandConstant('{app}\respaldos'), True, True, True);
      DelTree(ExpandConstant('{app}'), True, True, True);
    end;
  end;
end;
