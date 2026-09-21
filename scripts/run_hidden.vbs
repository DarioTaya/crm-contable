Set shell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

' 1. Determinar automáticamente la raíz del proyecto CRM (directorio padre de /scripts)
scriptDir = fso.GetParentFolderName(WScript.ScriptFullName)
projectRoot = fso.GetParentFolderName(scriptDir)

' Establecer por defecto el directorio de trabajo en la raíz del proyecto
shell.CurrentDirectory = projectRoot

' Asegurar que el directorio storage\logs exista siempre para evitar errores de cmd
logDir = projectRoot & "\storage\logs"
If Not fso.FolderExists(projectRoot & "\storage") Then
    On Error Resume Next
    fso.CreateFolder(projectRoot & "\storage")
    On Error Goto 0
End If
If Not fso.FolderExists(logDir) Then
    On Error Resume Next
    fso.CreateFolder(logDir)
    On Error Goto 0
End If

startIndex = 0
logFile = logDir & "\automation.log"

' Procesar argumentos de control opcionales:
' A. Si el primer argumento es una carpeta existente, usarla como CurrentDirectory
If WScript.Arguments.Count > startIndex Then
    firstArg = WScript.Arguments.Item(startIndex)
    If fso.FolderExists(firstArg) Then
        shell.CurrentDirectory = firstArg
        startIndex = startIndex + 1
    End If
End If

' B. Si el siguiente argumento contiene --log-file=..., usar esa ruta de log dedicada
If WScript.Arguments.Count > startIndex Then
    nextArg = WScript.Arguments.Item(startIndex)
    pos = InStr(nextArg, "--log-file=")
    If pos > 0 Then
        logFile = Replace(Mid(nextArg, pos + 11), """", "")
        startIndex = startIndex + 1
    End If
End If

' Construir la línea de comandos ejecutable
command = ""
For i = startIndex To WScript.Arguments.Count - 1
    arg = WScript.Arguments.Item(i)
    command = command & """" & Replace(arg, """", """""") & """ "
Next

' Si no hay comando que ejecutar, salir silenciosamente
If Trim(command) = "" Then
    WScript.Quit 0
End If

finalCmd = "cmd.exe /c (" & Trim(command) & " >> """ & logFile & """ 2>&1)"
shell.Run finalCmd, 0, False


