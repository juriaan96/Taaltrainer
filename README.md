# Taaltrainer

Een webapplicatie waarmee gebruikers woorden kunnen oefenen in een vreemde taal via quizvragen en herhalingsoefeningen.

## Wat doet de app?

De Taaltrainer helpt gebruikers bij het leren van woordenschat in een andere taal. De gebruiker krijgt woorden te zien en moet het juiste vertaalde woord invoeren of kiezen. Na elke oefening krijgt de gebruiker feedback en een score.

## Teamleden

| Naam | Rol |
|------|-----|
| Juriaan | Fullstack developer |

## Technologie

- HTML / CSS (Bootstrap)
- PHP (sessions, POST/GET)
- MySQL (database)
- JavaScript
- GitHub (versiebeheer)

## Mappenstructuur

```
/docs         - Projectdocumentatie
/design       - Moodboard en stijlkeuzes
/wireframes   - Schermontwerpen
/src          - Broncode van de applicatie
```

## Installatie

1. Clone de repository
2. Zet de bestanden in je webserver map (bijv. `/laragon/www/Taaltrainer`)
3. Importeer de database via `/docs/database.sql`
4. Pas de databaseverbinding aan in `/src/config.php`
5. Open de app via `http://localhost/Taaltrainer`
