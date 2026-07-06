// Constantes du site. Les valeurs marquées TODO sont à confirmer (agence / propriétaire).
export const SITE = {
  url: 'https://easyrest.eu',
  name: 'EasyRest Milano',
  // Adresse & géo de l'appartement (pour le schema LocalBusiness) — TODO confirmer
  address: 'Via Giovanni da Breganze, 20152 Milano (MI), Italia',
  geo: { lat: 45.4572, lng: 9.1458 },
};

export const BOOKING = {
  // Réservation = lead-gen. Le bouton ouvre WhatsApp ; l'agence confirme (<15 min) et encaisse via son PayPal.
  whatsapp: '393885822307', // WhatsApp de l'agence (+39 388 582 23 07)
  // Tarif de référence par nuit (€) — sert d'estimation quand le scraper live n'est pas
  // disponible (prix estimé = nuits × ce tarif × (1 - remise)). TODO: mettre le vrai tarif moyen.
  basePricePerNight: 120,

  discountPercent: 15, // « -X % vs Booking »
  email: 'contact@easyrest.eu', // TODO confirmer
  // Calendrier de disponibilités Guesty (iCal, lecture seule) importé au build.
  // Vide → le calendrier est masqué. Voir src/lib/guesty.ts.
  icalUrl: 'https://app.guesty.com/api/public/icalendar-dashboard-api/export/f8b62c16-65c8-48da-bf5a-a718f6ac0527',
  // Endpoint public /quote du microservice prix (services/price-scraper sur Hetzner).
  // Vide → le simulateur bascule en « prix estimé / sur demande » (WhatsApp avec les dates).
  quoteEndpoint: 'https://api.easyrest.eu/quote',
  ota: {
    booking: 'https://www.booking.com/hotel/it/easy-rest-affitti-brevi-italia.fr.html', // annonce Booking (tracking retiré)
    airbnb: 'https://www.airbnb.fr/rooms/1370963027643363967', // annonce Airbnb (tracking retiré)
  },
};
