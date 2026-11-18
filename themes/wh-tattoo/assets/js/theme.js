"use strict";

document.addEventListener("DOMContentLoaded", () => {
	const mobileNavigation = document.querySelector(
		".mobile-navigation"
	);

	if (mobileNavigation) {
		const mobileNavigationLinks =
			mobileNavigation.querySelectorAll("a");

		mobileNavigationLinks.forEach((link) => {
			link.addEventListener("click", () => {
				mobileNavigation.removeAttribute("open");
			});
		});
	}

	const contactMaps = document.querySelectorAll(
		"[data-contacts-map]"
	);

	contactMaps.forEach((map) => {
		const preview = map.querySelector(
			"[data-map-wake]"
		);

		if (!preview) {
			return;
		}

		const wakeMap = () => {
			map.classList.add("is-awake");

			preview.setAttribute(
				"aria-pressed",
				"true"
			);
		};

		const sleepMap = () => {
			map.classList.remove("is-awake");

			preview.setAttribute(
				"aria-pressed",
				"false"
			);
		};

		preview.addEventListener(
			"click",
			wakeMap
		);

		map.addEventListener(
			"mouseleave",
			() => {
				if (
					window.matchMedia(
						"(pointer: fine)"
					).matches
				) {
					sleepMap();
				}
			}
		);

		document.addEventListener(
			"keydown",
			(event) => {
				if (
					event.key === "Escape" &&
					map.classList.contains("is-awake")
				) {
					sleepMap();
					preview.focus();
				}
			}
		);

		if ("IntersectionObserver" in window) {
			const observer =
				new IntersectionObserver(
					(entries) => {
						const entry = entries[0];

						if (
							entry.isIntersecting &&
							entry.intersectionRatio >= 0.2
						) {
							return;
						}

						sleepMap();
					},
					{
						threshold: [
							0,
							0.2
						]
					}
				);

			observer.observe(map);
		}
	});

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

		let resizeTimer = null;
		let previousTrackWidth = track.clientWidth;

		window.addEventListener(
			"resize",
			() => {
				window.clearTimeout(resizeTimer);

				resizeTimer = window.setTimeout(() => {
					const currentTrackWidth = track.clientWidth;

					if (
						Math.abs(currentTrackWidth - previousTrackWidth) > 2
					) {
						track.scrollTo({
							left: 0,
							behavior: "auto"
						});

						previousTrackWidth = currentTrackWidth;
					}

					updateControls();
				}, 120);
			},
			{ passive: true }
		);

		const galleryStates = new WeakMap();

		const parseGalleryItems = (slide) => {
			const dataElement = slide.querySelector(
				"[data-portfolio-gallery-data]"
			);

			if (!dataElement) {
				return [];
			}

			const encodedData =
				dataElement.dataset
					.portfolioGalleryData;

			if (!encodedData) {
				return [];
			}

			try {
				/*
				 * Декодируем Base64 в байты,
				 * затем корректно восстанавливаем UTF-8.
				 * Это сохраняет русские alt-тексты.
				 */
				const binary = window.atob(
					encodedData
				);

				const bytes = Uint8Array.from(
					binary,
					(character) => (
						character.charCodeAt(0)
					)
				);

				const json = new TextDecoder(
					"utf-8"
				).decode(bytes);

				const items = JSON.parse(json);

				return Array.isArray(items)
					? items
					: [];
			} catch (error) {
				console.error(
					"WH Portfolio: не удалось прочитать галерею.",
					error
				);

				return [];
			}
		};

		const preloadGalleryItem = (
			state,
			index
		) => {
			const item = state.items[index];

			if (
				!item ||
				!item.url ||
				state.preloaded.has(index)
			) {
				return;
			}

			const preloadImage = new Image();

			preloadImage.decoding = "async";
			preloadImage.src = item.url;

			state.preloaded.add(index);
		};

		const getVisibleDotIndexes = (
			itemCount,
			activeIndex
		) => {
			if (itemCount <= 6) {
				return Array.from(
					{ length: itemCount },
					(_, index) => index
				);
			}

			const maximumStart =
				itemCount - 5;

			const start = Math.max(
				0,
				Math.min(
					activeIndex - 2,
					maximumStart
				)
			);

			return Array.from(
				{ length: 5 },
				(_, offset) => start + offset
			);
		};

		const renderGalleryDots = (
			state
		) => {
			if (!state.dots) {
				return;
			}

			const visibleIndexes =
				getVisibleDotIndexes(
					state.items.length,
					state.activeIndex
				);

			state.dots.replaceChildren();

			visibleIndexes.forEach(
				(itemIndex, position) => {
					const dot =
						document.createElement(
							"button"
						);

					dot.type = "button";
					dot.className =
						"portfolio-slide__dot";

					dot.dataset.galleryIndex =
						String(itemIndex);

					dot.setAttribute(
						"aria-label",
						`Показать ракурс ${
							itemIndex + 1
						}`
					);

					dot.setAttribute(
						"aria-pressed",
						String(
							itemIndex
							=== state.activeIndex
						)
					);

					dot.classList.toggle(
						"is-active",
						itemIndex
						=== state.activeIndex
					);

					const hasHiddenBefore =
						visibleIndexes[0] > 0;

					const hasHiddenAfter =
						visibleIndexes[
							visibleIndexes.length - 1
						] < state.items.length - 1;

					dot.classList.toggle(
						"is-edge",
						(
							position === 0 &&
							hasHiddenBefore
						) ||
						(
							position
							=== visibleIndexes.length - 1 &&
							hasHiddenAfter
						)
					);

					state.dots.append(dot);
				}
			);
		};

		const updateGalleryZones = (state) => {
			state.previousZone.disabled =
				state.activeIndex <= 0;

			state.nextZone.disabled =
				state.activeIndex >=
				state.items.length - 1;
		};

		const showGalleryItem = (
			state,
			index
		) => {
			const item = state.items[index];

			if (!item || !item.url) {
				return;
			}

			state.activeIndex = index;
			updateGalleryZones(state);
			state.image.src = item.url;
			state.image.alt = item.alt || "";

			state.media.classList.remove(
				"is-before"
			);

			renderGalleryDots(state);

			preloadGalleryItem(
				state,
				index + 1
			);
		};

		carousel
			.querySelectorAll(
				"[data-portfolio-slide]"
			)
			.forEach((slide) => {
				const media = slide.querySelector(
					".portfolio-slide__media"
				);

				const image = slide.querySelector(
					"[data-portfolio-image]"
				);

				const dots = slide.querySelector(
					"[data-portfolio-dots]"
				);

				const items =
					parseGalleryItems(slide);

				if (
					!media ||
					!image ||
					!dots ||
					items.length < 2
				) {
					return;
				}

				const previousZone =
					document.createElement("button");

				previousZone.type = "button";
				previousZone.className =
					"portfolio-slide__gallery-zone " +
					"portfolio-slide__gallery-zone--previous";

				previousZone.setAttribute(
					"aria-label",
					"Предыдущий ракурс"
				);

				const nextZone =
					document.createElement("button");

				nextZone.type = "button";
				nextZone.className =
					"portfolio-slide__gallery-zone " +
					"portfolio-slide__gallery-zone--next";

				nextZone.setAttribute(
					"aria-label",
					"Следующий ракурс"
				);

				media.append(
					previousZone,
					nextZone
				);

				const state = {
					slide,
					media,
					image,
					dots,
					items,
					previousZone,
					nextZone,
					activeIndex: 0,
					preloaded: new Set([0])
				};

				galleryStates.set(
					slide,
					state
				);

				previousZone.addEventListener(
					"click",
					() => {
						if (state.activeIndex <= 0) {
							return;
						}

						showGalleryItem(
							state,
							state.activeIndex - 1
						);
					}
				);

				nextZone.addEventListener(
					"click",
					() => {
						if (
							state.activeIndex >=
							state.items.length - 1
						) {
							return;
						}

						showGalleryItem(
							state,
							state.activeIndex + 1
						);
					}
				);

				updateGalleryZones(state);

				dots.addEventListener(
					"click",
					(event) => {
						const dot =
							event.target.closest(
								"[data-gallery-index]"
							);

						if (!dot) {
							return;
						}

						const index =
							Number.parseInt(
								dot.dataset
									.galleryIndex,
								10
							);

						if (
							!Number.isInteger(index)
						) {
							return;
						}

						showGalleryItem(
							state,
							index
						);
					}
				);

				renderGalleryDots(state);
			});

		/*
		 * Подготавливаем второй ракурс только для карточек,
		 * которые приблизились к области просмотра.
		 */
		if ("IntersectionObserver" in window) {
			const galleryObserver =
				new IntersectionObserver(
					(entries) => {
						entries.forEach(
							(entry) => {
								if (
									!entry.isIntersecting
								) {
									return;
								}

								const state =
									galleryStates.get(
										entry.target
									);

								if (state) {
									preloadGalleryItem(
										state,
										1
									);
								}

								galleryObserver.unobserve(
									entry.target
								);
							}
						);
					},
					{
						rootMargin:
							"300px 200px",
						threshold: 0
					}
				);

			carousel
				.querySelectorAll(
					"[data-portfolio-slide]"
				)
				.forEach((slide) => {
					if (
						galleryStates.has(slide)
					) {
						galleryObserver.observe(
							slide
						);
					}
				});
		} else {
			carousel
				.querySelectorAll(
					"[data-portfolio-slide]"
				)
				.forEach((slide) => {
					const state =
						galleryStates.get(slide);

					if (state) {
						preloadGalleryItem(
							state,
							1
						);
					}
				});
		}

		const compareButtons =
			carousel.querySelectorAll(
				"[data-portfolio-mode]"
			);

		compareButtons.forEach((button) => {
			button.addEventListener(
				"click",
				() => {
					const slide = button.closest(
						"[data-portfolio-slide]"
					);

					const media = button.closest(
						".portfolio-slide__media"
					);

					const image = media?.querySelector(
						"[data-portfolio-image]"
					);

					if (
						!slide ||
						!media ||
						!image
					) {
						return;
					}

					const mode =
						button.dataset
							.portfolioMode
						=== "before"
							? "before"
							: "after";

					const galleryState =
						galleryStates.get(slide);

					if (mode === "before") {
						const source =
							image.dataset.beforeSrc;

						if (!source) {
							return;
						}

						image.src = source;
						image.alt =
							image.dataset.beforeAlt
							|| "";

						media.classList.add(
							"is-before"
						);
					} else if (galleryState) {
						showGalleryItem(
							galleryState,
							galleryState.activeIndex
						);
					} else {
						const source =
							image.dataset.afterSrc;

						if (!source) {
							return;
						}

						image.src = source;
						image.alt =
							image.dataset.afterAlt
							|| "";

						media.classList.remove(
							"is-before"
						);
					}

					media
						.querySelectorAll(
							"[data-portfolio-mode]"
						)
						.forEach(
							(modeButton) => {
								modeButton.classList.toggle(
									"is-active",
									modeButton
										=== button
								);
							}
						);
				}
			);
		});

		updateControls();

		window.requestAnimationFrame(() => {
			track.scrollLeft = 0;
			updateControls();
		});
	});
});