"use strict";

document.addEventListener("DOMContentLoaded", () => {
	const galleries = document.querySelectorAll("[data-wh-gallery]");

	galleries.forEach((gallery) => {
		const dialog = gallery.querySelector("[data-gallery-dialog]");
		const dataElement = gallery.querySelector("[data-gallery-data]");
		const image = gallery.querySelector("[data-gallery-image]");
		const title = gallery.querySelector("[data-gallery-title]");
		const terms = gallery.querySelector("[data-gallery-terms]");
		const compare = gallery.querySelector("[data-gallery-compare]");
		const closeButton = gallery.querySelector("[data-gallery-close]");
		const previousButton = gallery.querySelector("[data-gallery-prev]");
		const nextButton = gallery.querySelector("[data-gallery-next]");
		const modeButtons = gallery.querySelectorAll("[data-gallery-mode]");
		const cardButtons = gallery.querySelectorAll("[data-gallery-index]");

		if (
			!dialog ||
			!dataElement ||
			!image ||
			!title ||
			!terms
		) {
			return;
		}

		image.setAttribute("draggable", "false");
		image.addEventListener("dblclick", (event) => {
			event.preventDefault();
		});

		let items = [];

		try {
			items = JSON.parse(dataElement.textContent);
		} catch (error) {
			console.error("Не удалось загрузить данные галереи.", error);
			return;
		}

		if (!Array.isArray(items) || items.length === 0) {
			return;
		}

		let currentIndex = 0;
		let currentMode = "after";

		const normalizeIndex = (index) => {
			if (index < 0) {
				return items.length - 1;
			}

			if (index >= items.length) {
				return 0;
			}

			return index;
		};

		const updateModeButtons = () => {
			modeButtons.forEach((button) => {
				button.classList.toggle(
					"is-active",
					button.dataset.galleryMode === currentMode
				);
			});
		};

		const render = () => {
			const item = items[currentIndex];

			if (!item) {
				return;
			}

			const hasBefore = Boolean(item.before);

			if (!hasBefore) {
				currentMode = "after";
			}

			const isBefore = currentMode === "before" && hasBefore;

			image.src = isBefore ? item.before : item.after;
			image.alt = isBefore ? item.before_alt : item.after_alt;
			title.textContent = item.title || "";
			terms.textContent = item.terms || "";

			if (compare) {
				compare.hidden = !hasBefore;
			}

			updateModeButtons();
		};

		const open = (index) => {
			currentIndex = normalizeIndex(index);
			currentMode = "after";
			render();

			if (typeof dialog.showModal === "function") {
				dialog.showModal();
			} else {
				dialog.setAttribute("open", "open");
			}
		};

		const close = () => {
			if (typeof dialog.close === "function") {
				dialog.close();
			} else {
				dialog.removeAttribute("open");
			}

			image.src = "";
			image.alt = "";
		};

		cardButtons.forEach((button) => {
			button.addEventListener("click", () => {
				open(Number.parseInt(button.dataset.galleryIndex, 10) || 0);
			});
		});

		closeButton?.addEventListener("click", close);

		previousButton?.addEventListener("click", () => {
			currentIndex = normalizeIndex(currentIndex - 1);
			currentMode = "after";
			render();
		});

		nextButton?.addEventListener("click", () => {
			currentIndex = normalizeIndex(currentIndex + 1);
			currentMode = "after";
			render();
		});

		modeButtons.forEach((button) => {
			button.addEventListener("click", () => {
				currentMode = button.dataset.galleryMode === "before" ? "before" : "after";
				render();
			});
		});

		dialog.addEventListener("click", (event) => {
			if (event.target === dialog) {
				close();
			}
		});

		document.addEventListener("keydown", (event) => {
			if (!dialog.open) {
				return;
			}

			if (event.key === "ArrowLeft") {
				currentIndex = normalizeIndex(currentIndex - 1);
				currentMode = "after";
				render();
			}

			if (event.key === "ArrowRight") {
				currentIndex = normalizeIndex(currentIndex + 1);
				currentMode = "after";
				render();
			}
		});
	});
});