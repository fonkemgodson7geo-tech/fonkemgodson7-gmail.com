' === CENTRE MEDICAL DONS DE SOINS - TIMETABLE GENERATOR V1.0 ===
' AUTO-ROTATION + MANUAL OVERRIDE

Sub GenerateTimetable() 
    Dim ws As Worksheet
    Dim month As Integer, year As Integer
    Dim startDate As Date, endDate As Date, currentDate As Date
    Dim row As Integer, dayNum As Integer
    Dim staff As Variant
    Dim lastRow As Integer, lastCol As Integer
    
    ' === EDIT THESE 3 LINES FOR EACH NEW MONTH ===
    month = 5  ' 1=Jan, 2=Feb, 3=Mar... 12=Dec
    year = 2026
    staff = Array("Abeng", "Mayan", "Nloga", "Favour", "Wiltitz", "Kadija", "Nyanze", _
                  "Mvogo", "Nagayena", "Ndong", "Zad", "Florinda", "Cathrine", "Abanda", "Saurel")
    ' =============================================
    
    Application.ScreenUpdating = False
    Application.DisplayAlerts = False
    
    ' Delete old sheet if exists
    On Error Resume Next
    ThisWorkbook.Sheets("CMDS_" & MonthName(month, True) & "_" & year).Delete
    On Error GoTo 0
    
    Set ws = ThisWorkbook.Sheets.Add
    ws.Name = "CMDS_" & MonthName(month, True) & "_" & year
    
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
    
    ' === AUTO-ROTATION RULES FROM YOUR OLD SHEET ===
    row = 2
    currentDate = startDate
    Do While currentDate <= endDate
        ws.Cells(row, 1) = Format(currentDate, "dd-mmm-yy")
        ws.Cells(row, 2) = Format(currentDate, "ddd")
        dayNum = Day(currentDate)
        
        ' Abeng - IDE: M/A rotation, Night every 7th day
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
        
        ' First Sunday = Inventaire
        If Weekday(currentDate) = 1 And dayNum <= 7 Then
            ws.Cells(row, UBound(staff) + 4) = "Inventaire"
        End If
        
        ' Cameroon public holidays 2026 - add manually in Notes if needed
        If month = 5 And dayNum = 1 Then ws.Cells(row, UBound(staff) + 4) = "Fête du Travail"
        If month = 5 And dayNum = 20 Then ws.Cells(row, UBound(staff) + 4) = "Fête Nationale"
        
        row = row + 1
        currentDate = currentDate + 1
    Loop
    
    ' === SETUP MANUAL OVERRIDE ===
    lastRow = row - 1
    lastCol = UBound(staff) + 3
    
    ' Header formatting
    With ws.Rows(1)
        .Font.Bold = True
        .Interior.Color = RGB(0, 86, 179)
        .Font.Color = RGB(255, 255, 255)
        .Font.Size = 11
    End With
    
    ' Freeze top row + first 2 columns
    ws.Activate
    ActiveWindow.FreezePanes = False
    ws.Range("C2").Select
    ActiveWindow.FreezePanes = True
    
    ' Dropdown for manual edits on all shift cells
    With ws.Range(ws.Cells(2, 3), ws.Cells(lastRow, lastCol)).Validation
        .Delete
        .Add Type:=xlValidateList, AlertStyle:=xlValidAlertStop, _
            Formula1:="M,A,N,J,G,R,REPOS,INV,F,8h-22h,22h-8h,21H-8H"
        .IgnoreBlank = True
        .InCellDropdown = True
        .ShowInput = True
        .InputTitle = "Modifier Shift"
        .InputMessage = "Choisir: M=Matin, A=Après-midi, N=Nuit, J=Journée, G=Garde, R=Repos"
        .ShowError = True
        .ErrorTitle = "Code invalide"
        .ErrorMessage = "Utilise seulement: M,A,N,J,G,R,REPOS,8h-22h,22h-8h"
    End With
    
    ' Conditional colors - matches your old sheet
    Call ApplyColors(ws, 2, 3, lastRow, lastCol)
    
    ' Auto-count shifts per person
    ws.Cells(lastRow + 1, 2) = "TOTAL M:"
    ws.Cells(lastRow + 2, 2) = "TOTAL A:"
    ws.Cells(lastRow + 3, 2) = "TOTAL N:"
    ws.Cells(lastRow + 4, 2) = "TOTAL R:"
    
    For i = 3 To lastCol
        ws.Cells(lastRow + 1, i).Formula = "=COUNTIF(" & ws.Cells(2, i).Address & ":" & ws.Cells(lastRow, i).Address & ",""M"")"
        ws.Cells(lastRow + 2, i).Formula = "=COUNTIF(" & ws.Cells(2, i).Address & ":" & ws.Cells(lastRow, i).Address & ",""A"")"
        ws.Cells(lastRow + 3, i).Formula = "=COUNTIF(" & ws.Cells(2, i).Address & ":" & ws.Cells(lastRow, i).Address & ",""N"")+COUNTIF(" & ws.Cells(2, i).Address & ":" & ws.Cells(lastRow, i).Address & ",""G"")"
        ws.Cells(lastRow + 4, i).Formula = "=COUNTIF(" & ws.Cells(2, i).Address & ":" & ws.Cells(lastRow, i).Address & ",""R"")+COUNTIF(" & ws.Cells(2, i).Address & ":" & ws.Cells(lastRow, i).Address & ",""REPOS"")"
    Next i
    
    ' Legend
    Dim legendRow As Integer
    legendRow = lastRow + 6
    ws.Cells(legendRow, 1).Font.Bold = True
    ws.Cells(legendRow, 1) = "LEGENDE - CODES SHIFT"
    ws.Cells(legendRow + 1, 1) = "M = Matin 8h-14h"
    ws.Cells(legendRow + 2, 1) = "A = Après-midi 14h-21h"
    ws.Cells(legendRow + 3, 1) = "N = Nuit 21h-8h"
    ws.Cells(legendRow + 4, 1) = "J = Journée 8h-22h"
    ws.Cells(legendRow + 5, 1) = "G = Garde 21h-8h"
    ws.Cells(legendRow + 6, 1) = "R = Repos | REPOS = Congé | INV = Inventaire | F = Férié"
    ws.Cells(legendRow + 8, 1).Font.Bold = True
    ws.Cells(legendRow + 8, 1) = "MODE D'EMPLOI:"
    ws.Cells(legendRow + 9, 1) = "1. AUTO: Les shifts sont pré-remplis selon rotation"
    ws.Cells(legendRow + 10, 1) = "2. MANUEL: Clique sur une cellule → Choisir dans la liste"
    ws.Cells(legendRow + 11, 1) = "3. URGENCE 24/7: Dr ABENG 681 629 527 | Dr TATA 673 080 473"
    
    ws.Columns("A:Q").AutoFit
    ws.Range("A1").Select
    
    Application.ScreenUpdating = True
    Application.DisplayAlerts = True
    
    MsgBox "Timetable " & MonthName(month) & " " & year & " créé!" & vbCrLf & _
           "✓ Auto-rotation appliquée" & vbCrLf & _
           "✓ Tu peux modifier manuellement avec les listes déroulantes" & vbCrLf & _
           "✓ Totaux en bas du tableau", vbInformation, "CMDS Timetable"
End Sub

Sub ApplyColors(ws As Worksheet, startRow As Integer, startCol As Integer, endRow As Integer, endCol As Integer)
    Dim rng As Range
    Set rng = ws.Range(ws.Cells(startRow, startCol), ws.Cells(endRow, endCol))
    rng.FormatConditions.Delete
    
    ' M = Light Blue
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""M""")
        .Interior.Color = RGB(173, 216, 230)
    End With
    ' A = Light Green
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""A""")
        .Interior.Color = RGB(144, 238, 144)
    End With
    ' N = Dark Blue + White text
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""N""")
        .Interior.Color = RGB(0, 0, 139)
        .Font.Color = RGB(255, 255, 255)
        .Font.Bold = True
    End With
    ' G = Navy + White text
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""G""")
        .Interior.Color = RGB(25, 25, 112)
        .Font.Color = RGB(255, 255, 255)
        .Font.Bold = True
    End With
    ' J = Orange
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""J""")
        .Interior.Color = RGB(255, 165, 0)
    End With
    ' R = Light Gray
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""R""")
        .Interior.Color = RGB(211, 211, 211)
    End With
    ' REPOS = Red + White text
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""REPOS""")
        .Interior.Color = RGB(220, 20, 60)
        .Font.Color = RGB(255, 255, 255)
        .Font.Bold = True
    End With
    ' 8h-22h = Gold
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""8h-22h""")
        .Interior.Color = RGB(255, 215, 0)
    End With
    ' 22h-8h = Purple + White text
    With rng.FormatConditions.Add(Type:=xlCellValue, Operator:=xlEqual, Formula1:="=""22h-8h""")
        .Interior.Color = RGB(128, 0, 128)
        .Font.Color = RGB(255, 255, 255)
    End With
End Sub
