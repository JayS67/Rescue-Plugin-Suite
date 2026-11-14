module.exports = {
  content: ['./includes/**/*.php', './assets/js/**/*.js'],
  corePlugins: { preflight: false },
  // Classes assembled by responsive widget settings rather than written literally.
  safelist: [{ pattern: /^(grid-cols|col-span)-(1|2|3|4|5|6)$/, variants: ['sm', 'md', 'lg'] }],
};
