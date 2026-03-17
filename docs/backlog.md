# Backlog – Taaltrainer

## Must Have (minimaal werkende app)

| # | User Story | Status |
|---|------------|--------|
| 1 | Als gebruiker wil ik een woordoefening kunnen starten zodat ik woorden kan leren | Done ✅ |
| 2 | Als gebruiker wil ik een woord zien en een vertaling kunnen invoeren | Done ✅ |
| 3 | Als gebruiker wil ik direct feedback krijgen (goed/fout) na mijn antwoord | Done ✅ |
| 4 | Als gebruiker wil ik aan het einde mijn score zien | Done ✅ |
| 5 | Als gebruiker wil ik kunnen inloggen met gebruikersnaam en wachtwoord | Done ✅ |
| 6 | Als docent wil ik woordenlijsten kunnen toevoegen en beheren | Done ✅ |
| 7 | Als docent wil ik woorden kunnen koppelen aan een woordenlijst | Done ✅ |
| 8 | Als gebruiker wil ik een woordenlijst kunnen kiezen om te oefenen | Done ✅ |

## Could Have (extra's als er tijd over is)

| # | User Story | Status |
|---|------------|--------|
| 9 | Als gebruiker wil ik mijn vorige scores kunnen terugzien | Done ✅ |
| 10 | Als gebruiker wil ik foute woorden opnieuw kunnen oefenen | Done ✅ |
| 11 | Als gebruiker wil ik de app ook goed kunnen gebruiken op mijn telefoon | Done ✅ |
| 12 | Als docent wil ik per leerling de resultaten kunnen inzien | Done ✅ |
| 13 | Als gebruiker wil ik kunnen kiezen tussen meerkeuze of invullen | Niet gebouwd |
| 14 | Als gebruiker wil ik een timer zien tijdens de oefening | Niet gebouwd |

## Extra (buiten originele scope gerealiseerd)

| Feature | Omschrijving |
|---------|-------------|
| Fuzzy matching | Antwoorden ≥75% gelijkend geven een halve punt |
| CSV-import | Woordenlijsten importeren vanuit Excel/CSV |
| Realtime multiplayer | 1v1 duels met polling en 3-secondentimer |
| Registreren | Leerlingen kunnen zelf een account aanmaken |
| Gebruikersbeheer | Docenten kunnen rollen wijzigen en accounts verwijderen |

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
| US13, US14 | | US1 t/m US12 |

> Foto van fysiek kanban bord: zie `/design/kanban.jpg`
