(() => {
  const DICE_REGEX = /(\d+d\d+(?:\s*[+\-]\s*\d+d?\d*)*)/gi;
  const TAG_HTML = (text) => `<span class="grimorio-spell-tag grimorio-spell-tag--dice">${text}</span>`;
  const SKIP_NODES = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEXTAREA', 'INPUT', 'OPTION']);

  function highlightDice(root) {
    if (!root || !root.querySelectorAll) return;
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode(node) {
        if (!node || !node.parentElement) return NodeFilter.FILTER_REJECT;
        if (SKIP_NODES.has(node.parentElement.tagName)) return NodeFilter.FILTER_REJECT;
        if (!node.nodeValue || !DICE_REGEX.test(node.nodeValue)) return NodeFilter.FILTER_SKIP;
        return NodeFilter.FILTER_ACCEPT;
      },
    });

    const toProcess = [];
    let current = walker.nextNode();
    while (current) {
      toProcess.push(current);
      current = walker.nextNode();
    }

    toProcess.forEach((textNode) => {
      const content = textNode.nodeValue;
      if (!content) return;
      const replaced = content.replace(DICE_REGEX, TAG_HTML('$1'));
      if (replaced === content) return;
      const span = document.createElement('span');
      span.innerHTML = replaced;
      textNode.parentNode.replaceChild(span, textNode);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => highlightDice(document.body));
  } else {
    highlightDice(document.body);
  }
})();
