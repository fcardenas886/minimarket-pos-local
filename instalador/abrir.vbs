' Abre Minimarket POS en una ventana propia (Microsoft Edge en modo aplicación,
' sin barra de direcciones). Si Edge no está, usa el navegador predeterminado.
Option Explicit
Dim sh, fso, url, rutas, r, edge
Set sh = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")
url = "http://127.0.0.1:8090/"

rutas = Array( _
  sh.ExpandEnvironmentStrings("%ProgramFiles(x86)%") & "\Microsoft\Edge\Application\msedge.exe", _
  sh.ExpandEnvironmentStrings("%ProgramFiles%") & "\Microsoft\Edge\Application\msedge.exe", _
  sh.ExpandEnvironmentStrings("%LocalAppData%") & "\Microsoft\Edge\Application\msedge.exe")

edge = ""
For Each r In rutas
  If fso.FileExists(r) Then
    edge = r
    Exit For
  End If
Next

If edge <> "" Then
  ' --kiosk-printing: tickets y comandas se imprimen directo en la impresora predeterminada, sin diálogo.
  sh.Run """" & edge & """ --app=" & url & " --start-maximized --kiosk-printing", 1, False
Else
  sh.Run url, 1, False
End If
