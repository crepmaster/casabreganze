// Langues & chaînes d'interface. Le contenu éditorial (guides) vit dans les fichiers Markdown ;
// ici on ne stocke que les libellés d'UI.
export const languages = {
  fr: 'Français',
  en: 'English',
  it: 'Italiano',
  es: 'Español',
} as const;

export type Lang = keyof typeof languages;
export const defaultLang: Lang = 'fr';
export const langList = Object.keys(languages) as Lang[];

export const ui = {
  fr: {
    'nav.home': 'L’appartement',
    'nav.guides': 'Que faire à Milan',
    'hero.title': 'Votre nid douillet à Milan',
    'hero.subtitle': 'Appartement entier, lumineux et bien desservi — réservez en direct et économisez.',
    'booking.title': 'Réservez votre séjour',
    'booking.lead': 'Réponse de notre équipe en moins de 15 minutes.',
    'booking.cta_whatsapp': 'Réserver en direct sur WhatsApp',
    'booking.discount': 'Jusqu’à -{n}% par rapport à Booking',
    'booking.ota_intro': 'Vous préférez les plateformes ?',
    'booking.ota_booking': 'Voir sur Booking.com',
    'booking.ota_airbnb': 'Voir sur Airbnb',
    'booking.wa_message': 'Bonjour, je souhaite réserver l’appartement EasyRest à Milan. Mes dates : ',
    'guides.title': 'Que faire à Milan cette semaine',
    'guides.intro': 'Nos guides hebdomadaires : événements, expositions, bonnes adresses autour de l’appartement.',
    'guides.read': 'Lire le guide',
    'guides.back': '← Tous les guides',
    'footer.rights': 'Tous droits réservés.',
  },
  en: {
    'nav.home': 'The apartment',
    'nav.guides': 'What to do in Milan',
    'hero.title': 'Your cosy home in Milan',
    'hero.subtitle': 'A bright, well-connected entire apartment — book direct and save.',
    'booking.title': 'Book your stay',
    'booking.lead': 'Our team replies in under 15 minutes.',
    'booking.cta_whatsapp': 'Book direct on WhatsApp',
    'booking.discount': 'Up to {n}% cheaper than Booking',
    'booking.ota_intro': 'Prefer the platforms?',
    'booking.ota_booking': 'View on Booking.com',
    'booking.ota_airbnb': 'View on Airbnb',
    'booking.wa_message': 'Hello, I would like to book the EasyRest apartment in Milan. My dates: ',
    'guides.title': 'What to do in Milan this week',
    'guides.intro': 'Our weekly guides: events, exhibitions and local tips around the apartment.',
    'guides.read': 'Read the guide',
    'guides.back': '← All guides',
    'footer.rights': 'All rights reserved.',
  },
  it: {
    'nav.home': 'L’appartamento',
    'nav.guides': 'Cosa fare a Milano',
    'hero.title': 'La tua casa accogliente a Milano',
    'hero.subtitle': 'Appartamento intero, luminoso e ben collegato — prenota diretto e risparmia.',
    'booking.title': 'Prenota il tuo soggiorno',
    'booking.lead': 'Il nostro team risponde in meno di 15 minuti.',
    'booking.cta_whatsapp': 'Prenota diretto su WhatsApp',
    'booking.discount': 'Fino al {n}% in meno rispetto a Booking',
    'booking.ota_intro': 'Preferisci le piattaforme?',
    'booking.ota_booking': 'Vedi su Booking.com',
    'booking.ota_airbnb': 'Vedi su Airbnb',
    'booking.wa_message': 'Buongiorno, vorrei prenotare l’appartamento EasyRest a Milano. Le mie date: ',
    'guides.title': 'Cosa fare a Milano questa settimana',
    'guides.intro': 'Le nostre guide settimanali: eventi, mostre e consigli locali vicino all’appartamento.',
    'guides.read': 'Leggi la guida',
    'guides.back': '← Tutte le guide',
    'footer.rights': 'Tutti i diritti riservati.',
  },
  es: {
    'nav.home': 'El apartamento',
    'nav.guides': 'Qué hacer en Milán',
    'hero.title': 'Tu hogar acogedor en Milán',
    'hero.subtitle': 'Apartamento entero, luminoso y bien comunicado — reserva directo y ahorra.',
    'booking.title': 'Reserva tu estancia',
    'booking.lead': 'Nuestro equipo responde en menos de 15 minutos.',
    'booking.cta_whatsapp': 'Reservar directo por WhatsApp',
    'booking.discount': 'Hasta un {n}% más barato que Booking',
    'booking.ota_intro': '¿Prefieres las plataformas?',
    'booking.ota_booking': 'Ver en Booking.com',
    'booking.ota_airbnb': 'Ver en Airbnb',
    'booking.wa_message': 'Hola, quiero reservar el apartamento EasyRest en Milán. Mis fechas: ',
    'guides.title': 'Qué hacer en Milán esta semana',
    'guides.intro': 'Nuestras guías semanales: eventos, exposiciones y consejos locales cerca del apartamento.',
    'guides.read': 'Leer la guía',
    'guides.back': '← Todas las guías',
    'footer.rights': 'Todos los derechos reservados.',
  },
} as const;

export function t(lang: Lang, key: keyof (typeof ui)['fr']): string {
  return (ui[lang] as Record<string, string>)[key] ?? (ui.fr as Record<string, string>)[key];
}
