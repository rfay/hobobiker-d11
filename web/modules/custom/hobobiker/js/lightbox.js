/**
 * @file
 * Photos open in an overlay, as the original's lightbox did: links with rel="lightbox[group][caption]" show
 * their image over the page, grouped by "group", with "Image N of M", previous and next.
 * Keys as the original was set: close c, x, Esc; previous p, Left; next n, Right. No looping.
 */
((Drupal, once) => {
  let box;
  let items = [];
  let index = 0;

  // rel="lightbox[group1][A bunch of roses]" => group "group1", caption "A bunch of roses".
  const parse = (link) => {
    const m = /^lightbox(?:\[([^\]]*)\])?(?:\[(.*)\])?$/.exec(link.getAttribute('rel') || '');
    return { group: m ? m[1] || '' : '', caption: m && m[2] ? m[2] : '' };
  };

  const build = () => {
    box = document.createElement('div');
    box.className = 'hobobiker-lightbox';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.hidden = true;
    box.innerHTML = '<div class="hobobiker-lightbox__overlay"></div><figure class="hobobiker-lightbox__frame">'
      + '<img alt=""><figcaption><span class="hobobiker-lightbox__caption"></span>'
      + '<span class="hobobiker-lightbox__count"></span></figcaption>'
      + '<button type="button" class="hobobiker-lightbox__prev" aria-label="Previous image">‹</button>'
      + '<button type="button" class="hobobiker-lightbox__next" aria-label="Next image">›</button>'
      + '<button type="button" class="hobobiker-lightbox__close" aria-label="Close">×</button></figure>';
    document.body.appendChild(box);
    box.querySelector('.hobobiker-lightbox__prev').addEventListener('click', () => show(index - 1));
    box.querySelector('.hobobiker-lightbox__next').addEventListener('click', () => show(index + 1));
    box.querySelector('.hobobiker-lightbox__close').addEventListener('click', close);
    document.addEventListener('keydown', (event) => {
      if (box.hidden) {
        return;
      }
      const key = event.key.toLowerCase();
      if (['c', 'x', 'escape'].includes(key)) {
        close();
      }
      else if (['p', 'arrowleft'].includes(key)) {
        show(index - 1);
      }
      else if (['n', 'arrowright'].includes(key)) {
        show(index + 1);
      }
    });
  };

  const show = (i) => {
    if (i < 0 || i >= items.length) {
      return;
    }
    index = i;
    const link = items[i];
    const img = box.querySelector('img');
    img.src = link.href;
    img.alt = parse(link).caption;
    box.querySelector('.hobobiker-lightbox__caption').textContent = parse(link).caption;
    box.querySelector('.hobobiker-lightbox__count').textContent = items.length > 1 ? `Image ${i + 1} of ${items.length}` : '';
    box.querySelector('.hobobiker-lightbox__prev').hidden = i === 0;
    box.querySelector('.hobobiker-lightbox__next').hidden = i === items.length - 1;
    box.hidden = false;
    box.querySelector('.hobobiker-lightbox__close').focus();
  };

  const close = () => {
    box.hidden = true;
  };

  Drupal.behaviors.hobobikerLightbox = {
    attach(context) {
      once('hobobiker-lightbox', 'a[rel^="lightbox"]', context).forEach((link) => {
        link.addEventListener('click', (event) => {
          if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey) {
            return;
          }
          event.preventDefault();
          if (!box) {
            build();
          }
          const { group } = parse(link);
          items = [...document.querySelectorAll('a[rel^="lightbox"]')].filter((a) => parse(a).group === group);
          show(items.indexOf(link));
        });
      });
    },
  };
})(Drupal, once);
