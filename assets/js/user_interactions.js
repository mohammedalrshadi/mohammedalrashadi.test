/**
 * User Platform — Client Interactions (Likes, Bookmarks, Reading History)
 * assets/js/user_interactions.js
 */

(function () {
  'use strict';

  // Floating Toast Notification
  function showToast(message, type = 'info') {
    let toast = document.getElementById('platform-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'platform-toast';
      toast.className = 'fixed bottom-6 right-6 z-50 px-4 py-3 rounded-xl font-mono text-xs shadow-2xl transition-all duration-300 transform translate-y-4 opacity-0 pointer-events-none flex items-center gap-2 border';
      document.body.appendChild(toast);
    }

    if (type === 'success') {
      toast.className = 'fixed bottom-6 right-6 z-50 px-4 py-3 rounded-xl font-mono text-xs shadow-2xl transition-all duration-300 transform translate-y-0 opacity-100 flex items-center gap-2 bg-surface-container border border-primary/40 text-text-primary';
    } else if (type === 'error') {
      toast.className = 'fixed bottom-6 right-6 z-50 px-4 py-3 rounded-xl font-mono text-xs shadow-2xl transition-all duration-300 transform translate-y-0 opacity-100 flex items-center gap-2 bg-surface-container border border-red-500/40 text-red-400';
    } else {
      toast.className = 'fixed bottom-6 right-6 z-50 px-4 py-3 rounded-xl font-mono text-xs shadow-2xl transition-all duration-300 transform translate-y-0 opacity-100 flex items-center gap-2 bg-surface-container border border-border text-text-primary';
    }

    toast.innerHTML = message;

    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(() => {
      toast.classList.add('opacity-0', 'translate-y-4');
      toast.classList.remove('opacity-100', 'translate-y-0');
    }, 3500);
  }

  document.addEventListener('DOMContentLoaded', () => {
    const bars = document.querySelectorAll('.user-interaction-bar');
    if (!bars.length) return;

    bars.forEach(bar => {
      const contentType = bar.getAttribute('data-content-type');
      const contentId = bar.getAttribute('data-content-id');
      const trackHistory = bar.getAttribute('data-track-history') === 'true';

      if (!contentType || !contentId) return;

      const likeBtn = bar.querySelector('.user-like-btn');
      const bookmarkBtn = bar.querySelector('.user-bookmark-btn');
      const likeCountEl = bar.querySelector('.user-like-count');
      const likeIcon = bar.querySelector('.user-like-icon');
      const bmIcon = bar.querySelector('.user-bm-icon');

      let isAuth = false;
      let csrfToken = '';
      let isLiked = false;
      let isBookmarked = false;

      // 1. Fetch live status
      fetch(`/api/likes/status.php?content_type=${encodeURIComponent(contentType)}&content_id=${encodeURIComponent(contentId)}`)
        .then(res => res.json())
        .then(data => {
          if (!data.success) return;

          isAuth = !!data.is_authenticated;
          csrfToken = data.csrf_token || '';
          isLiked = !!data.liked;
          isBookmarked = !!data.bookmarked;

          // Update Like UI
          if (likeCountEl && data.likes_count !== undefined) {
            likeCountEl.textContent = data.likes_count;
          }
          updateLikeUI(isLiked);
          updateBookmarkUI(isBookmarked);

          // 2. Track reading history if enabled and user is logged in
          if (trackHistory && isAuth) {
            initHistoryTracker(contentType, contentId, csrfToken);
          }
        })
        .catch(err => console.error('[user_interactions] Status fetch error:', err));

      function updateLikeUI(liked) {
        if (!likeBtn) return;
        if (liked) {
          likeBtn.classList.add('text-red-500', 'border-red-500/40', 'bg-red-500/10');
          likeBtn.classList.remove('text-text-secondary', 'border-border');
          if (likeIcon) likeIcon.textContent = 'favorite';
        } else {
          likeBtn.classList.remove('text-red-500', 'border-red-500/40', 'bg-red-500/10');
          likeBtn.classList.add('text-text-secondary', 'border-border');
          if (likeIcon) likeIcon.textContent = 'favorite_border';
        }
      }

      function updateBookmarkUI(bookmarked) {
        if (!bookmarkBtn) return;
        if (bookmarked) {
          bookmarkBtn.classList.add('text-primary', 'border-primary/40', 'bg-primary/10');
          bookmarkBtn.classList.remove('text-text-secondary', 'border-border');
          if (bmIcon) bmIcon.textContent = 'bookmark';
        } else {
          bookmarkBtn.classList.remove('text-primary', 'border-primary/40', 'bg-primary/10');
          bookmarkBtn.classList.add('text-text-secondary', 'border-border');
          if (bmIcon) bmIcon.textContent = 'bookmark_border';
        }
      }

      // Handle Like Click
      if (likeBtn) {
        likeBtn.addEventListener('click', async (e) => {
          e.preventDefault();
          if (!isAuth) {
            const redirectUrl = encodeURIComponent(window.location.pathname + window.location.search);
            showToast(`<span class="material-symbols-outlined text-primary text-[18px]">lock</span> <span><a href="/login.php?redirect=${redirectUrl}" class="underline font-bold text-primary">Sign in</a> to like this content.</span>`, 'info');
            return;
          }

          likeBtn.disabled = true;
          try {
            const res = await fetch('/api/likes/toggle.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
              },
              body: JSON.stringify({ content_type: contentType, content_id: contentId })
            });
            const data = await res.json();
            if (data.success) {
              isLiked = !!data.liked;
              updateLikeUI(isLiked);
              if (likeCountEl && data.likes_count !== undefined) {
                likeCountEl.textContent = data.likes_count;
              }
              showToast(isLiked ? '<span class="material-symbols-outlined text-red-500 text-[18px]">favorite</span> Added to your liked collection.' : 'Removed from liked collection.', 'success');
            } else {
              showToast(data.message || 'Action failed', 'error');
            }
          } catch (err) {
            showToast('Failed to toggle like', 'error');
          } finally {
            likeBtn.disabled = false;
          }
        });
      }

      // Handle Bookmark Click
      if (bookmarkBtn) {
        bookmarkBtn.addEventListener('click', async (e) => {
          e.preventDefault();
          if (!isAuth) {
            const redirectUrl = encodeURIComponent(window.location.pathname + window.location.search);
            showToast(`<span class="material-symbols-outlined text-primary text-[18px]">lock</span> <span><a href="/login.php?redirect=${redirectUrl}" class="underline font-bold text-primary">Sign in</a> to bookmark for later.</span>`, 'info');
            return;
          }

          bookmarkBtn.disabled = true;
          try {
            const res = await fetch('/api/bookmarks/toggle.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
              },
              body: JSON.stringify({ content_type: contentType, content_id: contentId })
            });
            const data = await res.json();
            if (data.success) {
              isBookmarked = !!data.bookmarked;
              updateBookmarkUI(isBookmarked);
              showToast(isBookmarked ? '<span class="material-symbols-outlined text-primary text-[18px]">bookmark</span> Saved to your dashboard bookmarks.' : 'Removed from bookmarks.', 'success');
            } else {
              showToast(data.message || 'Action failed', 'error');
            }
          } catch (err) {
            showToast('Failed to toggle bookmark', 'error');
          } finally {
            bookmarkBtn.disabled = false;
          }
        });
      }
    });

    // History tracking function
    function initHistoryTracker(contentType, contentId, csrfToken) {
      let maxProgress = 0;
      let recorded = false;

      function calcProgress() {
        const target = document.querySelector('[data-progress-target]') || document.querySelector('.prose') || document.querySelector('article');
        
        let totalHeight;
        let currentScroll;

        if (target) {
          const rect = target.getBoundingClientRect();
          totalHeight = rect.height;
          // Calculate how much of the target has been scrolled past
          // If the top of the target is below the viewport, we haven't started (0)
          // We add window.innerHeight so that progress is 100% when the *bottom* of the target is in view.
          const scrollDistance = (window.innerHeight - rect.top);
          
          if (totalHeight <= 0) return 100;
          let p = (scrollDistance / totalHeight) * 100;
          return Math.min(100, Math.max(5, Math.round(p)));
        } else {
          totalHeight = document.documentElement.scrollHeight - window.innerHeight;
          if (totalHeight <= 0) return 100;
          currentScroll = window.scrollY || window.pageYOffset;
          return Math.min(100, Math.max(5, Math.round((currentScroll / totalHeight) * 100)));
        }
      }

      function recordHistory(progress) {
        if (!csrfToken) return;
        fetch('/api/history/record.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify({
            content_type: contentType,
            content_id: contentId,
            progress_percent: progress
          })
        }).catch(() => { });
      }

      // Initial record after 3 seconds on page
      setTimeout(() => {
        maxProgress = calcProgress();
        recordHistory(maxProgress);
        recorded = true;
      }, 3000);

      // On scroll update maxProgress
      window.addEventListener('scroll', () => {
        const p = calcProgress();
        if (p > maxProgress) {
          maxProgress = p;
        }
      }, { passive: true });

      // On page leave update progress if changed significantly
      window.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden' && recorded) {
          recordHistory(maxProgress);
        }
      });
    }

    // Product Download Tracking
    const downloadLinks = document.querySelectorAll('.js-record-download');
    if (downloadLinks.length > 0) {
      downloadLinks.forEach(link => {
        link.addEventListener('click', async () => {
          const productId = link.getAttribute('data-product-id');
          if (!productId) return;

          // Helper to fetch CSRF token from status endpoint
          async function fetchCsrfToken() {
            try {
              const res = await fetch('/api/likes/status.php?content_type=product&content_id=' + encodeURIComponent(productId));
              if (!res.ok) return null;
              const data = await res.json();
              if (data && data.is_authenticated && data.csrf_token) {
                let meta = document.querySelector('meta[name="csrf-token"]');
                if (!meta) {
                  meta = document.createElement('meta');
                  meta.name = 'csrf-token';
                  document.head.appendChild(meta);
                }
                meta.setAttribute('content', data.csrf_token);
                return data.csrf_token;
              }
              return null;
            } catch (_) {
              return null;
            }
          }

          let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
          if (!csrf) {
            // Initial attempt to fetch CSRF token
            csrf = await fetchCsrfToken();
            // Retry CSRF fetch once if the initial fallback network round-trip failed
            if (!csrf) {
              csrf = await fetchCsrfToken();
            }
          }

          // If visitor is unauthenticated, no CSRF token is available; skip claim silently
          if (!csrf) {
            return;
          }

          async function postClaim(token) {
            return fetch('/api/products/claim.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': token
              },
              body: JSON.stringify({ id: parseInt(productId, 10) })
            });
          }

          try {
            let res = await postClaim(csrf);

            // If 403 (possible stale or mismatched CSRF token), refresh token and retry claim once
            if (res.status === 403) {
              const freshToken = await fetchCsrfToken();
              if (freshToken && freshToken !== csrf) {
                csrf = freshToken;
                res = await postClaim(freshToken);
              }
            }

            if (!res.ok) {
              let errorMsg = 'HTTP ' + res.status;
              let errorData = null;
              try {
                errorData = await res.json();
                if (errorData && errorData.message) {
                  errorMsg = errorData.message;
                }
              } catch (_) {}
              console.warn(`Download recording failed: ${errorMsg}`, {
                productId: productId,
                status: res.status,
                data: errorData
              });
            }
          } catch (netErr) {
            console.warn(`Download recording failed: Network error (${netErr.message || 'unknown'})`, {
              productId: productId,
              error: netErr
            });
          }
        });
      });
    }
  });
})();

