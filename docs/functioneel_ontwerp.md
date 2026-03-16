# Functioneel Ontwerp – Taaltrainer

## Scherm 1 – Startscherm / Inloggen

**Wat ziet de gebruiker?**
- Logo en naam van de app
- Invoervelden voor gebruikersnaam en wachtwoord
- Knop "Inloggen"

**Wat kan de gebruiker doen?**
- Inloggen met bestaand account

**Wat doet de app?**
- Controleert gebruikersnaam en wachtwoord via de database
- Bij succes: doorsturen naar het keuze-/startscherm
- Bij fout: foutmelding tonen ("Gebruikersnaam of wachtwoord onjuist")

---

## Scherm 2 – Woordenlijst kiezen

**Wat ziet de gebruiker?**
- Overzicht van beschikbare woordenlijsten (naam + aantal woorden)
- Knop "Starten" per woordenlijst

**Wat kan de gebruiker doen?**
- Een woordenlijst selecteren en de oefening starten

**Wat doet de app?**
- Laadt beschikbare woordenlijsten uit de database
- Slaat de gekozen woordenlijst op in de sessie

---

## Scherm 3 – Oefenscherm

**Wat ziet de gebruiker?**
- Het te vertalen woord (bijv. "kat")
- Een invoerveld voor het antwoord
- Huidige vraagnummer en totaal (bijv. "Vraag 3 van 10")
- Knop "Controleer"

**Wat kan de gebruiker doen?**
- Antwoord invoeren en bevestigen

**Wat doet de app?**
- Vergelijkt het ingevoerde antwoord met het correcte antwoord (niet hoofdlettergevoelig)
- Toont direct feedback: groen (goed) of rood (fout) + het juiste antwoord
- Gaat door naar het volgende woord na bevestiging
- Houdt de score bij in de sessie

---

## Scherm 4 – Resultaatscherm

**Wat ziet de gebruiker?**
- Eindscore (bijv. "8 van 10 goed")
- Overzicht van foute woorden met het correcte antwoord
- Knop "Opnieuw oefenen"
- Knop "Andere woordenlijst kiezen"

**Wat kan de gebruiker doen?**
- Resultaat bekijken
- Opnieuw beginnen of een andere lijst kiezen

**Wat doet de app?**
- Toont de opgeslagen score uit de sessie
- Slaat het resultaat op in de database (optioneel: voor statistieken)

---

## Scherm 5 – Beheerpaneel (docent)

**Wat ziet de gebruiker?**
- Overzicht van alle woordenlijsten
- Mogelijkheid om een nieuwe lijst aan te maken
- Mogelijkheid om woorden toe te voegen, aan te passen of te verwijderen

**Wat kan de docent doen?**
- CRUD-bewerkingen op woordenlijsten en woorden

**Wat doet de app?**
- Slaat wijzigingen op in de database
- Alleen toegankelijk voor gebruikers met de rol "docent"

---

## Overzicht kernfuncties

| Functie | Beschrijving |
|---------|-------------|
| Inloggen | Authenticatie via sessie |
| Woordenlijst kiezen | Overzicht uit database |
| Woord tonen | Willekeurig of op volgorde uit geselecteerde lijst |
| Antwoord invoeren | Tekstinvoer, niet hoofdlettergevoelig |
| Feedback geven | Direct na antwoord: goed/fout + correct antwoord |
| Score bijhouden | Via PHP sessie gedurende de oefening |
| Eindresultaat tonen | Score + overzicht foute woorden |
| Woordbeheer (docent) | CRUD via beheerpaneel |
