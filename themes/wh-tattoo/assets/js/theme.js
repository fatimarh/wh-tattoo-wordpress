"use strict";

document.addEventListener("DOMContentLoaded", () => {
	const carousels = document.querySelectorAll("[data-portfolio-carousel]");

	carousels.forEach((carousel) => {
		const track = carousel.querySelector("[data-portfolio-track]");
		const previousButton = carousel.querySelector("[data-portfolio-previous]");
		const nextButton = carousel.querySelector("[data-portfolio-next]");
		const progress = carousel.querySelector("[data-portfolio-progress]");

		if (!track) {
			return;
		}

		const getScrollStep = () => {
			const slide = track.querySelector("[data-portfolio-slide]");

			if (!slide) {
				return track.clientWidth;
			}

			const styles = window.getComputedStyle(track);
			const gap = Number.parseFloat(styles.columnGap) || 0;

			return slide.getBoundingClientRect().width + gap;
		};

		const updateControls = () => {
			const maximumScroll = track.scrollWidth - track.clientWidth;
			const currentScroll = Math.max(0, track.scrollLeft);

			if (previousButton) {
				previousButton.disabled = currentScroll <= 2;
			}

			if (nextButton) {
				nextButton.disabled = currentScroll >= maximumScroll - 2;
			}

			if (progress) {
				const ratio = maximumScroll > 0 ? currentScroll / maximumScroll : 1;
				progress.style.transform = `scaleX(${Math.max(0.08, ratio)})`;
			}
		};

		previousButton?.addEventListener("click", () => {
			track.scrollBy({
				left: -getScrollStep(),
				behavior: "smooth"
			});
		});

		nextButton?.addEventListener("click", () => {
			track.scrollBy({
				left: getScrollStep(),
				behavior: "smooth"
			});
		});

		track.addEventListener("scroll", updateControls, { passive: true });
		window.addEventListener("resize", updateControls, { passive: true });

		const compareButtons = carousel.querySelectorAll("[data-portfolio-mode]");

		compareButtons.forEach((button) => {
			button.addEventListener("click", () => {
				const media = button.closest(".portfolio-slide__media");
				const image = media?.querySelector("[data-portfolio-image]");

				if (!media || !image) {
					return;
				}

				const mode = button.dataset.portfolioMode === "before" ? "before" : "after";
				const source = mode === "before" ? image.dataset.beforeSrc : image.dataset.afterSrc;
				const alternative = mode === "before" ? image.dataset.beforeAlt : image.dataset.afterAlt;

				if (!source) {
					return;
				}

				image.src = source;
				image.alt = alternative || "";

				media
					.querySelectorAll("[data-portfolio-mode]")
					.forEach((modeButton) => {
						modeButton.classList.toggle("is-active", modeButton === button);
					});
			});
		});

		updateControls();
	});
});