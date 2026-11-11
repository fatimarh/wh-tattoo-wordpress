"use strict";

document.addEventListener("DOMContentLoaded", () => {
	const reviewBlocks = document.querySelectorAll(
		"[data-wh-reviews]"
	);

	reviewBlocks.forEach((block) => {
		const dialog = block.querySelector(
			"[data-review-dialog]"
		);

		const dataElement = block.querySelector(
			"[data-review-data]"
		);

		const dialogText = block.querySelector(
			"[data-review-dialog-text], .wh-review-dialog__text"
		);

		const dialogAuthor = block.querySelector(
			"[data-review-dialog-author], #wh-review-dialog-author"
		);

		const dialogDate = block.querySelector(
			"[data-review-dialog-date], .wh-review-dialog__date"
		);

		const dialogLink = block.querySelector(
			"[data-review-dialog-link], .wh-review-dialog__link"
		);

		const closeButton = block.querySelector(
			"[data-review-close]"
		);

		if (
			!dialog ||
			!dataElement ||
			!dialogText ||
			!dialogAuthor ||
			!dialogDate ||
			!dialogLink
		) {
			console.error(
				"WH Reviews: отсутствуют элементы окна отзыва.",
				{
					dialog,
					dataElement,
					dialogText,
					dialogAuthor,
					dialogDate,
					dialogLink
				}
			);

			return;
		}

		let reviews;

		try {
			reviews = JSON.parse(
				dataElement.textContent.trim()
			);
		} catch (error) {
			console.error(
				"WH Reviews: не удалось прочитать JSON.",
				error
			);

			return;
		}

		if (!Array.isArray(reviews)) {
			console.error(
				"WH Reviews: данные отзывов не являются массивом."
			);

			return;
		}

		let opener = null;

		const closeDialog = () => {
			if (typeof dialog.close === "function") {
				if (dialog.open) {
					dialog.close();
				}
			} else {
				dialog.removeAttribute("open");
			}
		};

		block.addEventListener("click", (event) => {
			const button = event.target.closest(
				"[data-review-open]"
			);

			if (!button || !block.contains(button)) {
				return;
			}

			const index = Number.parseInt(
				button.dataset.reviewOpen,
				10
			);

			if (
				!Number.isInteger(index) ||
				!reviews[index]
			) {
				console.error(
					"WH Reviews: отзыв не найден.",
					index
				);

				return;
			}

			const review = reviews[index];

			dialogText.textContent = review.text || "";
			dialogAuthor.textContent = review.author || "";
			dialogDate.textContent = review.date || "";

			if (review.source_url) {
				dialogLink.href = review.source_url;
				dialogLink.hidden = false;
			} else {
				dialogLink.removeAttribute("href");
				dialogLink.hidden = true;
			}

			opener = button;

			if (typeof dialog.showModal === "function") {
				dialog.showModal();
			} else {
				dialog.setAttribute("open", "");
			}
		});

		closeButton?.addEventListener(
			"click",
			closeDialog
		);

		dialog.addEventListener("click", (event) => {
			if (event.target === dialog) {
				closeDialog();
			}
		});

		dialog.addEventListener("close", () => {
			if (opener) {
				opener.focus();
				opener = null;
			}
		});

		document.addEventListener("keydown", (event) => {
			if (
				event.key === "Escape" &&
				dialog.hasAttribute("open")
			) {
				closeDialog();
			}
		});
	});
});
