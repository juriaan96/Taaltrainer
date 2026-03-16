# Backlog – Taaltrainer

## Must Have (minimaal werkende app)

| # | User Story | Status |
|---|------------|--------|
| 1 | Als gebruiker wil ik een woordoefening kunnen starten zodat ik woorden kan leren | To-do |
| 2 | Als gebruiker wil ik een woord zien en een vertaling kunnen invoeren | To-do |
| 3 | Als gebruiker wil ik direct feedback krijgen (goed/fout) na mijn antwoord | To-do |
| 4 | Als gebruiker wil ik aan het einde mijn score zien | To-do |
| 5 | Als gebruiker wil ik kunnen inloggen met gebruikersnaam en wachtwoord | To-do |
| 6 | Als docent wil ik woordenlijsten kunnen toevoegen en beheren | To-do |
| 7 | Als docent wil ik woorden kunnen koppelen aan een woordenlijst | To-do |
| 8 | Als gebruiker wil ik een woordenlijst kunnen kiezen om te oefenen | To-do |

## Could Have (extra's als er tijd over is)

| # | User Story |
|---|------------|
| 9 | Als gebruiker wil ik mijn vorige scores kunnen terugzien |
| 10 | Als gebruiker wil ik foute woorden opnieuw kunnen oefenen |
| 11 | Als gebruiker wil ik de app ook goed kunnen gebruiken op mijn telefoon |
| 12 | Als docent wil ik per leerling de resultaten kunnen inzien |
| 13 | Als gebruiker wil ik kunnen kiezen tussen meerkeuze of invullen |
| 14 | Als gebruiker wil ik een timer zien tijdens de oefening |

---

## Afhankelijkheden

```
[5] Inloggen
    └── [8] Woordenlijst kiezen
        └── [2] Woord tonen + invoeren
            └── [3] Feedback tonen
                └── [4] Score tonen

[6] Woordenlijsten beheren (docent)
    └── [7] Woorden koppelen
        └── [8] Woordenlijst kiezen (gebruiker)
```

---

## Kanban bord

| To-do | Doing | Done |
|-------|-------|------|
| US1 t/m US8 | | |

> Foto van fysiek kanban bord: zie `/design/kanban.jpg`
