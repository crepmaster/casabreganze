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
  whatsapp: '393000000000', // TODO: numéro WhatsApp réel de l'agence (format international, sans +)
  discountPercent: 15, // « -X % vs Booking »
  email: 'contact@easyrest.eu', // TODO confirmer
  ota: {
    booking: 'https://www.booking.com/', // TODO: lien profond de l'annonce Booking
    airbnb: 'https://www.airbnb.com/', // TODO: lien profond de l'annonce Airbnb
  },
};
