' CENTRE MEDICAL DONS DE SOINS - TIMETABLE SYSTEM V4.0 FINAL
' Features: Auto-Rotation | Manual Override | PDF Export | Email Staff
' ============================================================================

Sub CreateDashboard()
    Dim ws As Worksheet
    Dim btnGenerate As Button, btnPDF As Button, btnEmail As Button
    
    Application.DisplayAlerts = False
    On Error Resume Next
    ThisWorkbook.Sheets("DASHBOARD").Delete
    On Error GoTo 0
    Application.DisplayAlerts = True
    
    Set ws = ThisWorkbook.Sheets.Add(Before:=ThisWorkbook.Sheets(1))
    ws.Name = "DASHBOARD"
    
    ' Title
    With ws.Range("B2")
        .Value = "CENTRE MEDICAL DONS DE SOINS"
        .Font.Size = 16
        .Font.Bold = True
        .Font.Color = RGB(0, 86, 179)
    End With
    ws.Range("B3").Value = "Système de Planning Mensuel - V4.0 FINAL"
    ws.Range("B3").Font.Italic = True
    
    ' Instructions
    ws.Range("B5").Font.Bold = True
    ws.Range("B5").Value = "WORKFLOW:"
    ws.Range("B6").Value = "1. Choisis le mois/année → Clique GÉNÉRER"
    ws.Range("B7").Value = "2. Modifie le tableau manuellement si besoin"
    ws.Range("B8").Value = "3. Clique PDF pour WhatsApp ou EMAIL pour envoi direct"
    
    ' Input cells
    ws.Range("B10").Font.Bold = True
    ws.Range("B10").Value = "PARAMÈTRES:"
    ws.Range("B11").Value = "Mois (1-12):"
    ws.Range("C11").Value = Month(Date)
    ws.Range("C11").Interior.Color = RGB(255, 255, 0)
    ws.Range("C11").Font.Bold = True
    ws.Range("C11").Name = "SelectedMonth"
    
    ws.Range("B12").Value = "Année:"
    ws.Range("C12").Value = Year(Date)
    ws.Range("C12").Interior.Color = RGB(255, 255, 0)
    ws.Range("C12").Font.Bold = True
    ws.Range("C12").Name = "SelectedYear"
    
    ' Button 1: Generate
    Set btnGenerate = ws.Buttons.Add(ws.Range("B14").Left, ws.Range("B14").Top, 260, 38)
    With btnGenerate
        .OnAction = "GenerateTimetableFromDashboard"
        .Caption = "1. GÉNÉRER TIMETABLE DU MOIS"
        .Font.Size = 10
        .Font.Bold = True
    End With
    
    ' Button 2: PDF Export
    Set btnPDF = ws.Buttons.Add(ws.Range("B16").Left, ws.Range("B16").Top, 260, 38)
    With btnPDF
        .OnAction = "ExportToPDF"
        .Caption = "2. EXPORT PDF POUR WHATSAPP"
        .Font.Size = 10
        .Font.Bold = True
    End With
    
    ' Button 3: Email
    Set btnEmail = ws.Buttons.Add(ws.Range("B18").Left, ws.Range("B18").Top, 260, 38)
    With btnEmail
        .OnAction = "EmailToStaff"
        .Caption = "3. ENVOYER EMAIL AU STAFF"
        .Font.Size = 10
        .Font.Bold = True
    End With
    
    ' Staff emails - EDIT THESE WITH REAL EMAILS
    ws.Range("E10").Font.Bold = True
    ws.Range("E10").Value = "EMAILS DU STAFF - MODIFIE ICI:"
    ws.Range("E11").Value = "Abeng: abeng@cmds.cm"
    ws.Range("E12").Value = "Mayan: mayan@cmds.cm"
    ws.Range("E13").Value = "Nloga: nloga@cmds.cm"
    ws.Range("E14").Value = "Favour: favour@cmds.cm"
    ws.Range("E15").Value = "Wiltitz: wiltitz@cmds.cm"
    ws.Range("E16").Value = "Kadija: kadija@cmds.cm"
    ws.Range("E17").Value = "Nyanze: nyanze@cmds.cm"
    ws.Range("E18").Value = "Mvogo: mvogo@cmds.cm"
    ws.Range("E19").Value = "Nagayena: nagayena@cmds.cm"
    ws.Range("E20").Value = "Ndong: ndong@cmds.cm"
    ws.Range("E21").Value = "Zad: zad@cmds.cm"
    ws.Range("E22").Value = "Florinda: florinda@cmds.cm"
    ws.Range("E23").Value = "Cathrine: cathrine@cmds.cm"
    ws.Range("E24").Value = "Abanda: abanda@cmds.cm"
    ws.Range("E25").Value = "Saurel: saurel@cmds.cm"
    
    ' Legend
    ws.Range("B21").Font.Bold = True
    ws.Range("B21").Value = "CODES SHIFT UTILISÉS:"
    ws.Range("B22").Value = "M = Matin 8h-14h | A = Après-midi 14h-21h | N = Nuit 21h-8h"
    ws.Range("B23").Value = "J = Journée 8h-22h | G = Garde 21h-8h | R = Repos | REPOS = Congé"
    ws.Range("B24").Value = "INV = Inventaire | F = Férié"
    
    ws.Range("B26").Font.Bold = True
    ws.Range("B26").Value = "CONTACT URGENCE 24/7:"
    ws.Range("B27").Value = "Dr ABENG: 681 629 527"
    ws.Range("B28").Value = "Dr TATA: 673 080 473"
    
    ws.Columns("A:F").AutoFit
    ws.Activate
    ws.Range("C11").Select
    
    MsgBox "Dashboard V4.0 FINAL créé!" & vbCrLf & _
           "✓ 3 boutons: Générer + PDF + Email" & vbCrLf & _
           "✓ IMPORTANT: Mets les vrais emails en colonne E", vbInformation
End Sub

Sub GenerateTimetableFromDashboard()
    Dim month As Integer, year As Integer
    Dim ws As Worksheet
    Dim startDate As Date, endDate As Date, currentDate As Date
    Dim row As Integer, dayNum As Integer
    Dim staff As Variant
    Dim lastRow As Integer, lastCol As Integer
    Dim sheetName As String
    
    month = ThisWorkbook.Sheets("DASHBOARD").Range("C11").Value
    year = ThisWorkbook.Sheets("DASHBOARD").Range("C12").Value
    
    If month < 1 Or month > 12 Or year < 2024 Or year > 2030 Then
        MsgBox "Mois invalide! Utilise 1-12. Année 2024-2030", vbCritical
        Exit Sub
    End If
    
    staff = Array("Abeng", "Mayan", "Nloga", "Favour", "Wiltitz", "Kadija", "Nyanze", _
                  "Mvogo", "Nagayena", "Ndong", "Zad", "Florinda", "Cathrine", "Abanda", "Saurel")
    
    Application.ScreenUpdating = False
    Application.DisplayAlerts = False
    
    sheetName = "CMDS_" & MonthName(month, True) & "_" & year
    On Error Resume Next
    ThisWorkbook.Sheets(sheetName).Delete
    On Error GoTo 0
    
    Set ws = ThisWorkbook.Sheets.Add(After:=ThisWorkbook.Sheets(ThisWorkbook.Sheets.Count))
    ws.Name = sheetName
    
    startDate = DateSerial(year, month, 1)
    endDate = DateSerial(year, month + 1, 0)
    
    ' HEADERS
    ws.Cells(1, 1) = "Date"
    ws.Cells(1, 2) = "Day"
    Dim i As Integer
    For i = 0 To UBound(staff)
        ws.Cells(1, i + 3) = staff(i)
    Next i
    ws.Cells(1, UBound(staff) + 4) = "Notes"
    
    ' === AUTO-ROTATION LOGIC BASED ON YOUR SCREENSHOT ===
    row = 2
    currentDate = startDate
    Do While currentDate <= endDate
        ws.Cells(row, 1) = Format(currentDate, "dd-mmm-yy")
        ws.Cells(row, 2) = Format(currentDate, "ddd")
        dayNum = Day(currentDate)
        
        ' Abeng - IDE Accoucheur: M/A rotation, N every 7th day
        ws.Cells(row, 3) = IIf(dayNum Mod 7 = 0, "N", IIf(dayNum Mod 2 = 0, "A", "M"))
        
        ' Mayan - Infirmière PEV: M Mon-Sat, R Sunday
        ws.Cells(row, 4) = IIf(Weekday(currentDate) = 1, "R", "M")
        
        ' Nloga - Labo: M/A rotation, R every 3rd day
        ws.Cells(row, 5) = IIf(dayNum Mod 3 = 0, "R", IIf(dayNum Mod 2 = 0, "A", "M"))
        
        ' Favour: Journée longue 8h-22h, R Sunday
        ws.Cells(row, 6) = IIf(Weekday(currentDate) = 1, "R", "J")
        
        ' Wiltitz: M, R every 6 days
        ws.Cells(row, 7) = IIf(dayNum Mod 6 = 0, "R", "M")
        
        ' Kadija: Night every 4 days, else M
        ws.Cells(row, 8) = IIf(dayNum Mod 4 = 0, "N", "M")
        
        ' Nyanze: A, R every 5 days
        ws.Cells(row, 9) = IIf(dayNum Mod 5 = 0, "R", "A")
        
        ' Mvogo: Garde 21H-8H every 3 days, else M
        ws.Cells(row, 10) = IIf(dayNum Mod 3 = 0, "G", "M")
        
        ' Nagayena: M always
        ws.Cells(row, 11) = "M"
        
        ' Ndong: A weekdays, R weekends
        ws.Cells(row, 12) = IIf(Weekday(currentDate) = 1 Or Weekday(currentDate) = 7, "R", "A")
        
        ' Zad: M, R every 4th day
        ws.Cells(row, 13) = IIf(dayNum Mod 4 = 1, "R", "M")
        
        ' Florinda: A always
        ws.Cells(row, 14) = "A"
        
        ' Cathrine: 8h-22h weekdays, 22h-8h Sunday
        ws.Cells(row, 15) = IIf(Weekday(currentDate) = 1, "22h-8h", "8h-22h")
        
        ' Abanda: 22h-8h, REPOS every 2 days
        ws.Cells(row, 16) = IIf(dayNum Mod 2 = 0, "REPOS", "22h-8h")
        
        ' Saurel: 8h-22h, REPOS every 3 days
        ws.Cells(row, 17) = IIf(dayNum Mod 3 = 0, "REPOS", "8h-22h")
        
        ' Cameroon holidays + Inventaire
        If month = 1 And dayNum = 1 Then ws.Cells(row, 18) = "Nouvel An"
        If month = 2 And dayNum = 11 Then ws.Cells(row, 18) = "Fête Jeunesse"
        If month = 5 And dayNum = 1 Then ws.Cells(row, 18) = "Fête du Travail"
        If month = 5 And dayNum = 20 Then ws.Cells(row, 18) = "Fête Nationale"
        If month = 8 And dayNum = 15 Then ws.Cells(row, 18) = "Assomption"
        If month = 12 And dayNum = 25 Then ws.Cells(row, 18) = "Noël"
        If Weekday(currentDate) = 1 And dayNum <= 7 Then ws.Cells(row, 18) = "Inventaire"
        
        row = row + 1
        currentDate = currentDate + 1
    Loop
    
    lastRow = row - 1
    lastCol = UBound(staff) + 3
    
    ' Format header
    With ws.Rows(1)
        .Font.Bold = True
        .Interior.Color = RGB(0, 86, 179)
        .Font.Color = RGB(255, 255, 255)
        .Font.Size = 10
    End With
    
    ' Freeze panes
    ws.Activate
    ActiveWindow.FreezePanes = False
    ws.Range("C2").Select
    ActiveWindow.FreezePanes = True
    
    ' Dropdown for manual edits
    With ws.Range(ws.Cells(2, 3), ws.Cells(lastRow, lastCol)).Validation
        .Delete
        .Add Type:=xlValidateList, AlertStyle:=xlValidAlertStop, _
            Formula1:="M,A,N,J,G,R,REPOS,INV,F,8h-22h,22h-8h,21H-8H"
        .IgnoreBlank = True
        .InCellDropdown = True
        .ShowInput = True
        .InputTitle = "Modifier Shift"
        .InputMessage = "Choisir: M=Matin, A=Après-midi, N=Nuit, J=Journée, G=Garde, R=Repos"
    End With
    
    ' Colors
    Call ApplyColors(ws, 2, 3, lastRow, lastCol)
    
    ' Totals per person
    ws.Cells(lastRow + 1, 2).Font.Bold = True
    ws.Cells(lastRow + 1, 2) = "TOTAL M:"
    ws.Cells(lastRow + 2, 2).Font.Bold = True
    ws.Cells(lastRow + 2, 2) = "TOTAL A:"
    ws.Cells(lastRow + 3, 2).Font.Bold = True
    ws.Cells(lastRow + 3, 2) = "TOTAL N/G:"
    ws.Cells(lastRow + 4, 2).Font.Bold = True
    ws.Cells(lastRow + 4, 2) = "TOTAL REPOS:"
    
    For i = 3 To lastCol
        ws.Cells(lastRow + 1, i).Formula = "=COUNTIF(" & ws.Cells(2, i).Address & ":" & ws.Cells(lastRow, i).Address & ",""M"")"
        ws.Cells(lastRow + 2, i).Formula = "=COUNTIF(" & ws.Cells(2, i).Address & ":" & ws.Cells(lastRow, i).Address & ",""A"")"
        ws.Cells(lastRow + 3, i).Formula = "=COUNTIF(" & ws.Cells(2, i).Address & ":" & ws.Cells(lastRow, i).Address & ",""N"")+COUNTIF(" & ws.Cells(2, i).Address & ":" & ws.Cells(lastRow, i).Address & ",""G"")"
        ws.Cells(lastRow + 4, i).Formula = "=COUNTIF(" & ws.Cells(2, i).Address & ":" & ws.Cells(lastRow, i).Address & ",""R"")+COUNTIF(" & ws.Cells(2, i).Address & ":" & ws.Cells(lastRow, i).Address & ",""REPOS"")"
    Next i
    
    ws.Columns("A:R").AutoFit
    Application.ScreenUpdating = True
    Application.DisplayAlerts = True
    
    MsgBox "Timetable " & MonthName(month) & " " & year & " créé avec succès!" & vbCrLf & vbCrLf & _
           "✓ Auto-rotation appliquée" & vbCrLf & _
           "✓ Clique sur une cellule pour modifier manuellement" & vbCrLf & _
           "✓ Utilise les boutons PDF ou EMAIL sur DASHBOARD", vbInformation
End Sub

Sub ExportToPDF()
    Dim ws As Worksheet
    Dim month As Integer, year As Integer
    Dim fileName As String, filePath As String
    Dim sheetName As String
    
    month = ThisWorkbook.Sheets("DASHBOARD").Range("C11").Value
    year = ThisWorkbook.Sheets("DASHBOARD").Range("C12").Value
    sheetName = "CMDS_" & MonthName(month, True) & "_" & year
    
    On Error Resume Next
    Set ws = ThisWorkbook.Sheets(sheetName)
    On Error GoTo 0
    
    If ws Is Nothing Then
        MsgBox "Génère d'abord le timetable pour " & MonthName(month) & " " & year & "!", vbExclamation
        Exit Sub
    End If
    
    fileName = "CMDS_Planning_" & MonthName(month, True) & "_" & year & ".pdf"
    filePath = CreateObject("WScript.Shell").SpecialFolders("Desktop") & "\" & fileName
    
    With ws.PageSetup
        .Orientation = xlLandscape
        .Zoom = False
        .FitToPagesWide = 1
        .FitToPagesTall = False
        .LeftMargin = Application.InchesToPoints(0.3)
        .RightMargin = Application.InchesToPoints(0.3)
        .TopMargin = Application.InchesToPoints(0.5)
        .BottomMargin = Application.InchesToPoints(0.5)
        .CenterHorizontally = True
        .PrintTitleRows = "$1:$1"
        .PrintTitleColumns = ""
    End With
    
    ws.ExportAsFixedFormat Type:=xlTypePDF, fileName:=filePath, _
        Quality:=xlQualityStandard, IncludeDocProperties:=True, _
        IgnorePrintAreas:=False, OpenAfterPublish:=True
    
    MsgBox "PDF exporté sur Bureau!" & vbCrLf & vbCrLf & _
           "Fichier: " & fileName & vbCrLf & vbCrLf & _
           "Prêt pour WhatsApp ou Email", vbInformation
End Sub

Sub EmailToStaff()
    Dim month As Integer, year As Integer
    Dim sheetName As String, ws As Worksheet
    Dim pdfPath As String, fileName As String
    Dim OutlookApp As Object, OutlookMail As Object
    Dim emailList As String
    Dim i As Integer, emailCount As Integer
    
    month = ThisWorkbook.Sheets("DASHBOARD").Range("C11").Value
    year = ThisWorkbook.Sheets("DASHBOARD").Range("C12").Value
    sheetName = "CMDS_" & MonthName(month, True) & "_" & year
    
    On Error Resume Next
    Set ws = ThisWorkbook.Sheets(sheetName)
    On Error GoTo 0
    
    If ws Is Nothing Then
        MsgBox "Génère d'abord le timetable!", vbExclamation
        Exit Sub
    End If
    
    ' Collect emails from Dashboard E11:E25
    emailList = ""
    emailCount = 0
    For i = 11 To 25
        If ThisWorkbook.Sheets("DASHBOARD").Cells(i, 5).Value <> "" Then
            If InStr(ThisWorkbook.Sheets("DASHBOARD").Cells(i, 5).Value, ":") > 0 Then
                emailList = emailList & Trim(Split(ThisWorkbook.Sheets("DASHBOARD").Cells(i, 5).Value, ":")(1)) & ";"
                emailCount = emailCount + 1
            End If
        End If
    Next i
    
    If emailList = "" Then
        MsgBox "Ajoute les emails du staff dans la colonne E du DASHBOARD!" & vbCrLf & _
               "Format: Abeng: email@domain.com", vbExclamation
        Exit Sub
    End If
    
    ' Create PDF
    fileName = "CMDS_Planning_" & MonthName(month, True) & "_" & year & ".pdf"
    pdfPath = CreateObject("WScript.Shell").SpecialFolders("Desktop") & "\" & fileName
    
    With ws.PageSetup
        .Orientation = xlLandscape
        .Zoom = False
        .FitToPagesWide = 1
        .CenterHorizontally = True
        .PrintTitleRows = "$1:$1"
    End With
    
    ws.ExportAsFixedFormat Type:=xlTypePDF, fileName:=pdfPath, Quality:=xlQualityStandard
    
    ' Create Outlook email
    On Error Resume Next
    Set OutlookApp = GetObject(class:="Outlook.Application")
    If OutlookApp Is Nothing Then Set OutlookApp = CreateObject(class:="Outlook.Application")
    On Error GoTo 0
    
    If OutlookApp Is Nothing Then
        MsgBox "Outlook n'est pas installé ou n'est pas ouvert!" & vbCrLf & _
               "Installe Outlook ou utilise le bouton PDF pour WhatsApp", vbCritical
        Exit Sub
    End If
    
    Set OutlookMail = OutlookApp.CreateItem(0)
    With OutlookMail
        .To = emailList
        .Subject = "Planning CMDS - " & MonthName(month) & " " & year
        .Body = "Bonjour l'équipe CENTRE MEDICAL DONS DE SOINS," & vbCrLf & vbCrLf & _
                "Veuillez trouver ci-joint le planning de travail pour " & MonthName(month) & " " & year & "." & vbCrLf & vbCrLf & _
                "LÉGENDE DES CODES:" & vbCrLf & _
                "M = Matin (8h-14h) | A = Après-midi (14h-21h) | N = Nuit (21h-8h)" & vbCrLf & _
                "J = Journée (8h-22h) | G = Garde (21h-8h) | R = Repos | REPOS = Congé" & vbCrLf & vbCrLf & _
                "IMPORTANT: Vérifiez vos shifts. En cas d'indisponibilité, contactez:" & vbCrLf & _
                "Dr ABENG: 681 629 527 | Dr TATA: 673 080 473" & vbCrLf & vbCrLf & _
                "Cordialement," & vbCrLf & _
                "Administration CMDS"
        .Attachments.Add pdfPath
        .Display ' Shows email for review. Change to .Send for auto-send
    End With
    
    Set OutlookMail = Nothing
    Set OutlookApp = Nothing
    
    MsgBox "Email Outlook ouvert!" & vbCrLf & vbCrLf & _
           "Destinataires: " & emailCount & " staff" & vbCrLf & _
           "PDF attaché: " & fileName & vbCrLf & vbCrLf & _
           "Vérifie et clique Envoyer", vbInformation
End Sub

Sub ApplyColors(ws As Worksheet, startRow As Integer, startCol As Integer, endRow As Integer, endCol As Integer)
    Dim rng As Range
    Set rng = ws.Range(ws.Cells(startRow, startCol), ws.Cells(endRow, endCol))
    rng.FormatConditions.Delete
    
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""M""")
        .Interior.Color = RGB(173, 216, 230)
    End With
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""A""")
        .Interior.Color = RGB(144, 238, 144)
    End With
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""N""")
        .Interior.Color = RGB(0, 0, 139): .Font.Color = RGB(255, 255, 255): .Font.Bold = True
    End With
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""G""")
        .Interior.Color = RGB(25, 25, 112): .Font.Color = RGB(255, 255, 255): .Font.Bold = True
    End With
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""J""")
        .Interior.Color = RGB(255, 165, 0)
    End With
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""R""")
        .Interior.Color = RGB(211, 211, 211)
    End With
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""REPOS""")
        .Interior.Color = RGB(220, 20, 60): .Font.Color = RGB(255, 255, 255): .Font.Bold = True
    End With
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""8h-22h""")
        .Interior.Color = RGB(255, 215, 0)
    End With
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""22h-8h""")
        .Interior.Color = RGB(128, 0, 128): .Font.Color = RGB(255, 255, 255)
    End With
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""21H-8H""")
        .Interior.Color = RGB(75, 0, 130): .Font.Color = RGB(255, 255, 255)
    End With
End Sub
