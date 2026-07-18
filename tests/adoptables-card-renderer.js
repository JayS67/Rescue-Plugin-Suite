#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'includes/modules/adoptables/class-module.php'), 'utf8');
const start = source.indexOf('  function cardTemplate(a){');
if (start < 0) throw new Error('cardTemplate was not found.');
let depth = 0;
let end = -1;
for (let i = source.indexOf('{', start); i < source.length; i++) {
  if (source[i] === '{') depth++;
  if (source[i] === '}' && --depth === 0) { end = i + 1; break; }
}
if (end < 0) throw new Error('cardTemplate has unbalanced braces.');

const context = {
  ASM_WIDGET: {
    imageProxyBase: 'https://example.test/wp-json/plugin/v1/animal-image',
    brandColor: '#401268',
    displayStyle: 'classic',
    enableFavourites: false,
    favouriteButtonPosition: 'top_left',
    // Simulate an obsolete public legacy layout. The renderer must fall back
    // instead of returning an empty wrapper and overlay button.
    builderCardOrder: ['old_image', 'old_title'],
  },
  getCatId: animal => String(animal.ANIMALID || ''),
  safeText: value => String(value || '').trim() || '—',
  proxyImageUrl: id => `https://example.test/wp-json/plugin/v1/animal-image?animalid=${encodeURIComponent(id)}&seq=1`,
  getReservationInfo: () => ({ show: false, label: '' }),
  reservationBadgePositionClasses: () => 'top-2 left-2',
  isFavourite: () => false,
  escHtml: value => String(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]),
  escAttr: value => String(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]),
  favouriteIconSvg: () => '<svg></svg>',
  Object,
};
vm.createContext(context);
vm.runInContext(`${source.slice(start, end)}; this.renderCard = cardTemplate;`, context);

const markup = context.renderCard({
  ANIMALID: '42',
  ANIMALNAME: 'Mittens',
  ANIMALAGE: '2 years',
  SEXNAME: 'Female',
  BREEDNAME: 'Domestic Shorthair',
});

for (const expected of ['asm-ad-card', '<img ', 'Mittens', 'Female', '2 years', 'Domestic Shorthair', 'asm-card-open']) {
  if (!markup.includes(expected)) throw new Error(`Rendered card is missing: ${expected}`);
}
console.log('Adoptables card renderer regression test passed');
