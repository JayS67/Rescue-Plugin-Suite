/* Restrict generated utilities and Tailwind variable defaults to suite-owned DOM. */
const fs = require('node:fs');
const postcss = require('postcss');
const selectorParser = require('postcss-selector-parser');
const roots = ['[data-rps-ui]'];
const scopeText = ':where([data-rps-ui])';
const scope = () => selectorParser().astSync(scopeText).first.first.clone();
function scopeSelector(value) {
  return selectorParser(selectors => {
    const output = [];
    selectors.each(selector => {
      const descendant = selector.clone();
      descendant.prepend(selectorParser.combinator({ value: ' ' }));
      descendant.prepend(scope());
      output.push(descendant);
      // Utilities can live on the root itself, e.g. .hidden or .fixed on a modal.
      const root = selector.clone();
      const boundary = root.nodes.find(node => node.type === 'combinator' || (node.type === 'pseudo' && (node.value.startsWith('::') || /^:(?:before|after|first-letter|first-line)$/.test(node.value))));
      if (boundary) root.insertBefore(boundary, scope());
      else root.append(scope());
      output.push(root);
    });
    selectors.removeAll();
    output.forEach(selector => selectors.append(selector));
  }).processSync(value, { lossless: false });
}
function scopeCss(css) {
  const tree = postcss.parse(css);
  const animations = new Map();
  tree.walkAtRules(/keyframes$/i, rule => {
    const name = rule.params.trim();
    animations.set(name, 'rps-tw-' + name);
    rule.params = 'rps-tw-' + name;
  });
  tree.walkDecls(/^animation(?:-name)?$/, declaration => {
    for (const [name, scoped] of animations) declaration.value = declaration.value.replace(new RegExp('\\b' + name + '\\b', 'g'), scoped);
  });
  tree.walkRules(rule => {
    let parent = rule.parent;
    while (parent) {
      if (parent.type === 'atrule' && /keyframes$/i.test(parent.name)) return;
      parent = parent.parent;
    }
    rule.selector = scopeSelector(rule.selector);
  });
  return tree.toString();
}
if (require.main === module) {
  const file = process.argv[2];
  if (!file) throw new Error('Usage: node scripts/scope-utilities.cjs path/to/utilities.css');
  fs.writeFileSync(file, scopeCss(fs.readFileSync(file, 'utf8')));
}
module.exports = { roots, scopeCss };
