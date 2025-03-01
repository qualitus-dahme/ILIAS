/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 ******************************************************************** */

/**
 * Transformations in the tiny dom
 */
export default class TinyDomTransform {
  /**
   * @type {boolean}
   */
  // debug = true;

  // tiny;

  constructor(tiny) {
    this.debug = true;
    this.tiny = tiny;
  }

  setAttribute(node, tag, attribute, value) {
    this.tiny.dom.setAttrib(this.tiny.dom.select(tag, node), attribute, value);
  }

  addListClasses(node) {
    this.setAttribute(node, 'ol', 'class', 'ilc_list_o_NumberedList');
    this.setAttribute(node, 'ul', 'class', 'ilc_list_u_BulletedList');
    this.setAttribute(node, 'li', 'class', 'ilc_list_item_StandardListItem');
  }

  replaceTag(node, tag, newTag, attributes) {
    const { dom } = this.tiny;
    tinyMCE.each(dom.select(tag, node), (n) => {
      dom.replace(dom.create(newTag, attributes, n.innerHTML), n);
    });
  }

  /**
   * This transforms multiple classes on a span into multiple spans with only one class.
   * Note: If you have nested a > b on a selction and b completely "overwrites a" another
   * application if a on the same selection will remove it (since it is already applied)
   * this might be an "invisible" effect, result: b only
   */
  nestMultiClasses(addedClass) {
    const r = this.tiny.dom.getRoot();
    const spans = Array.from(r.querySelectorAll('span'));

    spans.forEach((originalSpan) => {
      // Split the classes on whitespace
      const classList = originalSpan.className.split(/\s+/);

      // ensure, that the new class is always "at the end"
      const specialIndex = classList.indexOf(addedClass);
      if (specialIndex !== -1) {
        // Remove 'specialClass' from its current position
        classList.splice(specialIndex, 1);
        // Add 'specialClass' at the end
        classList.push(addedClass);
      }
      console.log(classList);

      // Only transform if more than one class
      if (classList.length > 1) {
        // Create the outermost span for the first class
        const newSpan = document.createElement('span');
        newSpan.className = classList[0];

        // We'll build nested spans inside it
        let current = newSpan;

        // For each additional class, create a nested span
        for (let i = 1; i < classList.length; i++) {
          const innerSpan = document.createElement('span');
          innerSpan.className = classList[i];
          current.appendChild(innerSpan);
          current = innerSpan;
        }

        // Move over all child nodes from the original to the innermost span
        while (originalSpan.firstChild) {
          current.appendChild(originalSpan.firstChild);
        }

        // Finally, replace the original span with our newly built nested structure
        originalSpan.replaceWith(newSpan);
      }
    });
  }

  replaceBoldUnderlineItalic(node) {
    this.replaceTag(node, 'b', 'span', { class: 'ilc_text_inline_Strong' });
    this.replaceTag(node, 'u', 'span', { class: 'ilc_text_inline_Important' });
    this.replaceTag(node, 'i', 'span', { class: 'ilc_text_inline_Emph' });
  }

  removeIds(node) {
    const { dom } = this.tiny;
    tinyMCE.each(dom.select('*[id!=""]', node), (el) => {
      el.id = '';
    });
  }

  /**
   * This one ensures that the standard ILIAS list style classes
   * are assigned to list elements
   */
  fixListClasses() {
    const ed = this.tiny; let par; let
      r;
    const { dom } = ed;

    dom.addClass(dom.select('ol:not([class])'), 'ilc_list_o_NumberedList');
    dom.addClass(dom.select('ul:not([class])'), 'ilc_list_u_BulletedList');
    dom.addClass(dom.select('li:not([class])'), 'ilc_list_item_StandardListItem');
  }

  // remove all divs (used after pasting)
  splitDivs() {
    // split all divs in divs
    const ed = this.tiny;
    const divs = ed.dom.select('p > div');
    let k;
    for (k in divs) {
      ed.dom.split(divs[k].parentNode, divs[k]);
    }
  }

  splitTopBr() {
    let changed = false;

    const ed = this.tiny;
    ed.getContent(); // this line is imporant and seems to fix some things
    tinymce.each(ed.dom.select('br').reverse(), (b) => {
      // console.log(b);
      // return;

      try {
        const snode = ed.dom.getParent(b, 'p,li');
        if (snode.nodeName !== 'LI'
          && snode.childNodes.length !== 1) {
          //				ed.dom.split(snode, b);

          function trim(node) {
            let i; let
              children = node.childNodes;

            if (node.nodeType === 1 && node.getAttribute('_mce_type') === 'bookmark') return;

            for (i = children.length - 1; i >= 0; i--) trim(children[i]);

            if (node.nodeType !== 9) {
              // Keep non whitespace text nodes
              if (node.nodeType === 3 && node.nodeValue.length > 0) {
                // If parent element isn't a block or there isn't any useful contents for example "<p>   </p>"
                if (!t.isBlock(node.parentNode) || tinymce.trim(node.nodeValue).length > 0) return;
              }

              if (node.nodeType === 1) {
                // If the only child is a bookmark then move it up
                children = node.childNodes;
                if (children.length === 1 && children[0] && children[0].nodeType === 1 && children[0].getAttribute(
                  '_mce_type',
                ) === 'bookmark') node.parentNode.insertBefore(children[0], node);

                // Keep non empty elements or img, hr etc
                if (children.length || /^(br|hr|input|img)$/i.test(node.nodeName)) return;
              }

              t.remove(node);
            }
            return node;
          }

          const pe = snode;
          const e = b;
          if (pe && e) {
            var t = ed.dom; let r = t.createRng(); let bef; let aft; let
              pa;

            // Get before chunk
            r.setStart(pe.parentNode, t.nodeIndex(pe));
            r.setEnd(e.parentNode, t.nodeIndex(e));
            bef = r.extractContents();

            // Get after chunk
            r = t.createRng();
            r.setStart(e.parentNode, t.nodeIndex(e) + 1);
            r.setEnd(pe.parentNode, t.nodeIndex(pe) + 1);
            aft = r.extractContents();

            // Insert before chunk
            pa = pe.parentNode;
            pa.insertBefore(trim(bef), pe);
            // pa.insertBefore(bef, pe);

            // Insert after chunk
            pa.insertBefore(trim(aft), pe);
            // pa.insertBefore(aft, pe);
            t.remove(pe);

            //					return re || e;
            changed = true;
          }
        }
      } catch (ex) {
        // IE can sometimes fire an unknown runtime error so we just ignore it
      }
    });
    return changed;
  }

  /**
   * This function converts all <br /> into corresponding paragraphs
   * (server content comes with <br />, but tiny has all kind of issues
   * in "<br>" mode (e.g. IE cannot handle lists). So we use the more
   * reliable "<p>" mode of tiny.
   */
  splitBR() {
    let snode;
    const ed = tinyMCE.activeEditor;
    const r = ed.dom.getRoot();

    // STEP 1: Handle all top level <br />

    // make copy of root
    const rcopy = r.cloneNode(true);

    // remove all childs of top level
    for (var k = r.childNodes.length - 1; k >= 0; k--) {
      r.removeChild(r.childNodes[k]);
    }

    // cp -> current P
    let cp = ed.dom.create('p', {}, '');
    let cp_content = false; // has current P any content?
    let cc; let
      pc; // cc: currrent child (top level), pc: P child

    // walk through root copy and add content to emptied original root
    for (var k = 0; k < rcopy.childNodes.length; k++) {
      cc = rcopy.childNodes[k];

      // handle Ps on top level
      // main purpose: convert <p> ...<br />...</p> to <p>...</p><p>...</p>
      if (cc.nodeName == 'P') {
        // is there a current P with content? -> add it to top level
        if (cp_content) {
          r.appendChild(cp);
          cp = ed.dom.create('p', {}, '');
          cp_content = false;
        }

        // split all BRs into separate Ps on top level
        for (let i = 0; i < cc.childNodes.length; i++) {
          pc = cc.childNodes[i];
          if (pc.nodeName == 'BR') {
            // append the current p an create a new one
            r.appendChild(cp);
            cp = ed.dom.create('p', {}, '');
            cp_content = false;
          } else {
            // append the content to the current p
            cp.appendChild(pc.cloneNode(true));
            cp_content = true;
          }
        }

        // append current p and create a new one
        if (cp_content) {
          r.appendChild(cp);
          cp = ed.dom.create('p', {}, '');
          cp_content = false;
        }
      } else if (cc.nodeName == 'UL' || cc.nodeName == 'OL') {
        // UL and OL are simply appended to the root
        if (cp_content) {
          r.appendChild(cp);
          cp = ed.dom.create('p', {}, '');
          cp_content = false;
        }
        r.appendChild(rcopy.childNodes[k].cloneNode(true));
      } else {
        cp.appendChild(rcopy.childNodes[k].cloneNode(true));
        cp_content = true;
      }
    }
    if (cp_content) {
      r.appendChild(cp);
    }

    // STEP 2: Handle all non-top level <br />
    // this is the standard tiny br splitting (which fails in top level Ps)
    /*		tinymce.each(ed.dom.select('br').reverse(), function(b) {
     try {
     var snode = ed.dom.getParent(b, 'p,li');
     ed.dom.split(snode, b);
     } catch (ex) {
     // IE can sometimes fire an unknown runtime error so we just ignore it
     }
     }); */
    this.splitTopBr();

    // STEP 3: Clean up

    // remove brs (normally all should have been handled above)
    let c = ed.getContent();
    c = c.split('<br />').join('');
    c = c.split('\n').join('');
    ed.setContent(c);
  }

  // split all span classes that are direct "children of themselves"
  // fixes bug #13019
  splitSpans() {
    let k; const ed = tinyMCE.activeEditor; let s;
    const classes = ['ilc_text_inline_Strong', 'ilc_text_inline_Emph', 'ilc_text_inline_Important',
      'ilc_text_inline_Comment', 'ilc_text_inline_Quotation', 'ilc_text_inline_Accent'];

    for (let i = 0; i < classes.length; i++) {
      s = ed.dom.select(`span[class="${classes[i]}"] > span[class="${classes[i]}"]`);
      for (k in s) {
        ed.dom.split(s[k].parentNode, s[k]);
      }
    }
  }
}
