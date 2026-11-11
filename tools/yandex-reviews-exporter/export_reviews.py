from __future__ import annotations

import hashlib
import json
import sys
from datetime import datetime
from pathlib import Path
from typing import Any

from playwright.sync_api import (
    Browser,
    BrowserContext,
    Page,
    Playwright,
    TimeoutError as PlaywrightTimeoutError,
    sync_playwright,
)


SCRIPT_DIRECTORY = Path(__file__).resolve().parent
CONFIG_PATH = SCRIPT_DIRECTORY / "config.json"


def load_config() -> dict[str, Any]:
    """Загружает и проверяет локальную конфигурацию."""

    if not CONFIG_PATH.exists():
        raise FileNotFoundError(
            "Не найден config.json. "
            "Создайте его копированием config.example.json."
        )

    with CONFIG_PATH.open("r", encoding="utf-8") as config_file:
        config = json.load(config_file)

    required_fields = (
        "business_id",
        "organization_name",
        "source_url",
        "output_directory",
    )

    missing_fields = [
        field
        for field in required_fields
        if not str(config.get(field, "")).strip()
    ]

    if missing_fields:
        raise ValueError(
            "В config.json отсутствуют обязательные поля: "
            + ", ".join(missing_fields)
        )

    business_id = str(config["business_id"]).strip()
    source_url = str(config["source_url"]).strip()

    if business_id not in source_url:
        raise ValueError(
            "ID организации отсутствует в source_url. "
            f"Ожидался ID: {business_id}"
        )

    config["business_id"] = business_id
    config["organization_name"] = str(
        config["organization_name"]
    ).strip()

    config["headless"] = bool(
        config.get("headless", False)
    )

    config["timeout_ms"] = max(
        10_000,
        min(
            int(config.get("timeout_ms", 60_000)),
            180_000,
        ),
    )

    return config


def create_output_directory(
    config: dict[str, Any],
) -> Path:
    """Создаёт папку для диагностических файлов."""

    configured_directory = Path(
        str(config["output_directory"])
    )

    if configured_directory.is_absolute():
        output_directory = configured_directory
    else:
        output_directory = (
            SCRIPT_DIRECTORY / configured_directory
        )

    output_directory.mkdir(
        parents=True,
        exist_ok=True,
    )

    return output_directory


def create_browser(
    playwright: Playwright,
    headless: bool,
) -> Browser:
    """Запускает Chromium."""

    return playwright.chromium.launch(
        headless=headless,
        args=[
            "--disable-blink-features=AutomationControlled",
        ],
    )


def create_context(
    browser: Browser,
) -> BrowserContext:
    """Создаёт браузерный контекст с русской локалью."""

    return browser.new_context(
        locale="ru-RU",
        timezone_id="Europe/Moscow",
        viewport={
            "width": 1440,
            "height": 1000,
        },
        device_scale_factor=1,
        color_scheme="light",
        user_agent=(
            "Mozilla/5.0 "
            "(Windows NT 10.0; Win64; x64) "
            "AppleWebKit/537.36 "
            "(KHTML, like Gecko) "
            "Chrome/131.0.0.0 Safari/537.36"
        ),
    )


def open_organization_page(
    page: Page,
    config: dict[str, Any],
) -> None:
    """Открывает страницу отзывов организации."""

    page.set_default_timeout(
        config["timeout_ms"]
    )

    page.goto(
        config["source_url"],
        wait_until="domcontentloaded",
        timeout=config["timeout_ms"],
    )

    try:
        page.wait_for_load_state(
            "networkidle",
            timeout=15_000,
        )
    except PlaywrightTimeoutError:
        # Яндекс Карты постоянно выполняют фоновые запросы.
        # Отсутствие networkidle не считаем ошибкой.
        pass

    page.wait_for_timeout(4_000)


def verify_organization(
    page: Page,
    config: dict[str, Any],
) -> dict[str, Any]:
    """Проверяет, что открыта нужная карточка."""

    current_url = page.url
    page_title = page.title()

    body_text = page.locator("body").inner_text(
        timeout=config["timeout_ms"]
    )

    business_id_found = (
        config["business_id"] in current_url
        or config["business_id"] in page.content()
    )

    organization_name_found = (
        config["organization_name"].casefold()
        in body_text.casefold()
    )

    reviews_word_found = (
        "отзыв" in body_text.casefold()
    )

    return {
        "business_id": config["business_id"],
        "organization_name": config[
            "organization_name"
        ],
        "requested_url": config["source_url"],
        "current_url": current_url,
        "page_title": page_title,
        "business_id_found": business_id_found,
        "organization_name_found": (
            organization_name_found
        ),
        "reviews_word_found": reviews_word_found,
        "body_text_length": len(body_text),
    }


def save_diagnostics(
    page: Page,
    output_directory: Path,
    result: dict[str, Any],
) -> None:
    """Сохраняет страницу, скриншот и отчёт проверки."""

    timestamp = datetime.now().strftime(
        "%Y%m%d-%H%M%S"
    )

    screenshot_path = (
        output_directory
        / f"diagnostic-{timestamp}.png"
    )

    html_path = (
        output_directory
        / f"diagnostic-{timestamp}.html"
    )

    report_path = (
        output_directory
        / f"diagnostic-{timestamp}.json"
    )

    text_path = (
        output_directory
        / f"diagnostic-{timestamp}.txt"
    )

    page.screenshot(
        path=str(screenshot_path),
        full_page=True,
    )

    html_path.write_text(
        page.content(),
        encoding="utf-8",
    )

    text_path.write_text(
        page.locator("body").inner_text(),
        encoding="utf-8",
    )

    report = {
        "schema_version": 1,
        "checked_at": datetime.now().astimezone().isoformat(),
        **result,
        "files": {
            "screenshot": screenshot_path.name,
            "html": html_path.name,
            "text": text_path.name,
        },
    }

    report_path.write_text(
        json.dumps(
            report,
            ensure_ascii=False,
            indent=2,
        ),
        encoding="utf-8",
    )

    print()
    print("Диагностика сохранена:")
    print(f"  Скриншот: {screenshot_path}")
    print(f"  HTML:     {html_path}")
    print(f"  Текст:    {text_path}")
    print(f"  Отчёт:    {report_path}")


def print_result(
    result: dict[str, Any],
) -> None:
    """Выводит результат проверки."""

    print()
    print("Результат проверки карточки:")
    print(
        "  Business ID найден: "
        f"{result['business_id_found']}"
    )
    print(
        "  Название найдено:   "
        f"{result['organization_name_found']}"
    )
    print(
        "  Раздел отзывов:      "
        f"{result['reviews_word_found']}"
    )
    print(
        "  Заголовок страницы:  "
        f"{result['page_title']}"
    )
    print(
        "  Текущий URL:         "
        f"{result['current_url']}"
    )


def inspect_review_containers(
    page: Page,
) -> dict[str, int]:
    """Проверяет известные варианты контейнеров отзывов."""

    selectors = (
        ".business-review-view",
        "[class*='business-review-view']",
        "[itemprop='review']",
        "[data-review-id]",
        "article",
    )

    result: dict[str, int] = {}

    for selector in selectors:
        try:
            result[selector] = page.locator(
                selector
            ).count()
        except Exception:
            result[selector] = 0

    return result


def get_review_locator(page: Page):
    """Возвращает наиболее подходящий локатор отзывов."""

    preferred_selectors = (
        ".business-review-view",
        "[itemprop='review']",
        "[data-review-id]",
    )

    for selector in preferred_selectors:
        locator = page.locator(selector)

        if locator.count() > 0:
            return locator, selector

    fallback = page.locator(
        "[class*='business-review-view']"
    )

    if fallback.count() > 0:
        return fallback, (
            "[class*='business-review-view']"
        )

    return None, ""


def clean_text(value: str | None) -> str:
    """Нормализует пробелы в тексте."""

    if not value:
        return ""

    return " ".join(
        value.replace("\u00a0", " ").split()
    ).strip()


def create_review_id(
    author: str,
    published_at: str,
    review_text: str,
) -> str:
    """Создаёт стабильный локальный ID отзыва."""

    normalized_source = "|".join(
        (
            clean_text(author).casefold(),
            clean_text(published_at).casefold(),
            clean_text(review_text)[:160].casefold(),
        )
    )

    digest = hashlib.sha256(
        normalized_source.encode("utf-8")
    ).hexdigest()

    return f"yandex-{digest[:24]}"


def first_text(
    container,
    selectors: tuple[str, ...],
) -> str:
    """Возвращает текст первого найденного элемента."""

    for selector in selectors:
        try:
            locator = container.locator(
                selector
            ).first

            if locator.count() and locator.is_visible():
                text = clean_text(
                    locator.inner_text()
                )

                if text:
                    return text

        except Exception:
            continue

    return ""


def get_review_text(container) -> str:
    """Возвращает наиболее полный доступный текст отзыва."""

    selectors = (
        "[itemprop='reviewBody']",
        ".business-review-view__body-text",
        ".business-review-view__body",
        "[class*='body-text']",
        "[class*='review-text']",
    )

    candidates: list[str] = []

    for selector in selectors:
        try:
            elements = container.locator(
                selector
            )

            for index in range(
                elements.count()
            ):
                element = elements.nth(index)

                values = (
                    element.get_attribute(
                        "content"
                    ),
                    element.get_attribute(
                        "data-text"
                    ),
                    element.get_attribute(
                        "aria-label"
                    ),
                    element.text_content(),
                    element.inner_text(),
                )

                for value in values:
                    cleaned_value = clean_text(
                        value
                    )

                    if cleaned_value:
                        candidates.append(
                            cleaned_value
                        )

        except Exception:
            continue

    if not candidates:
        return ""

    # Полный текст почти всегда является самым длинным.
    return max(
        candidates,
        key=len,
    )


def first_attribute(
    container,
    selectors: tuple[str, ...],
    attribute: str,
) -> str:
    """Возвращает атрибут первого найденного элемента."""

    for selector in selectors:
        try:
            locator = container.locator(
                selector
            ).first

            if locator.count():
                value = locator.get_attribute(
                    attribute
                )

                if value:
                    return value.strip()

        except Exception:
            continue

    return ""


def detect_rating(container) -> int:
    """Определяет оценку отзыва."""

    rating_selectors = (
        "[itemprop='ratingValue']",
        "meta[itemprop='ratingValue']",
        "[aria-label*='оценка']",
        "[aria-label*='звезд']",
        "[aria-label*='звёзд']",
    )

    for selector in rating_selectors:
        try:
            locator = container.locator(
                selector
            ).first

            if not locator.count():
                continue

            candidates = (
                locator.get_attribute("content"),
                locator.get_attribute("aria-label"),
                locator.inner_text(),
            )

            for candidate in candidates:
                if not candidate:
                    continue

                for character in candidate:
                    if character in "12345":
                        return int(character)

        except Exception:
            continue

    try:
        active_stars = container.locator(
            "[class*='stars'] "
            "[class*='_full'], "
            "[class*='rating'] "
            "[class*='_active']"
        ).count()

        if 1 <= active_stars <= 5:
            return active_stars

    except Exception:
        pass

    return 0


def expand_review_container(
    container,
) -> bool:
    """Раскрывает полный текст конкретного отзыва."""

    expand_button = container.locator(
        ".business-review-view__expand"
    ).first

    if not expand_button.count():
        return False

    try:
        if not expand_button.is_visible():
            return False

        text_container = container.locator(
            ".spoiler-view__text-container"
        ).first

        text_before = ""

        if text_container.count():
            text_before = clean_text(
                text_container.inner_text()
            )

        # В Яндекс Картах это span с role="button".
        # DOM-click устойчивее обычного Playwright click.
        expand_button.evaluate(
            "(element) => element.click()"
        )

        try:
            container.page.wait_for_function(
                """
                (element) => {
                    const textBlock = element.querySelector(
                        ".spoiler-view__text"
                    );

                    const expandButton = element.querySelector(
                        ".business-review-view__expand"
                    );

                    return (
                        !textBlock
                        || !textBlock.classList.contains("_collapsed")
                        || !expandButton
                        || expandButton.getAttribute("aria-hidden") === "true"
                    );
                }
                """,
                arg=container.element_handle(),
                timeout=5_000,
            )
        except Exception:
            # Иногда текст обновляется, но Яндекс оставляет класс.
            container.page.wait_for_timeout(500)

        if not text_container.count():
            return True

        text_after = clean_text(
            text_container.inner_text()
        )

        return (
            len(text_after) > len(text_before)
            or not container.locator(
                ".spoiler-view__text._collapsed"
            ).count()
        )

    except Exception:
        return False


def extract_loaded_reviews(
    page: Page,
) -> tuple[list[dict[str, Any]], str]:
    """Извлекает уже загруженные отзывы."""

    review_locator, used_selector = (
        get_review_locator(page)
    )

    if review_locator is None:
        return [], ""

    reviews: list[dict[str, Any]] = []
    seen_keys: set[str] = set()

    review_count = review_locator.count()

    for index in range(review_count):
        container = review_locator.nth(index)

        try:
            expand_review_container(
                container
            )

            author = first_text(
                container,
                (
                    ".business-review-view__author",
                    "[itemprop='author']",
                    "[class*='author']",
                    "[class*='name']",
                ),
            )

            review_text = get_review_text(
                container
            )

            has_expand_control = False

            try:
                has_expand_control = (
                    container.locator(
                        ".spoiler-view__text._collapsed"
                    ).count()
                    > 0
                )
            except Exception:
                has_expand_control = False

            review_text = clean_text(
                review_text
            )

            collapsed_suffixes = (
                "… ещё",
                "... ещё",
                "… Еще",
                "... Еще",
            )

            for suffix in collapsed_suffixes:
                if review_text.endswith(suffix):
                    review_text = review_text[
                        :-len(suffix)
                    ].rstrip()

            review_date = first_text(
                container,
                (
                    ".business-review-view__date",
                    "[itemprop='datePublished']",
                    "time",
                    "[class*='date']",
                ),
            )

            external_id = (
                container.get_attribute(
                    "data-review-id"
                )
                or container.get_attribute(
                    "data-id"
                )
                or ""
            ).strip()

            rating = detect_rating(container)

            if not review_text:
                continue

            if not external_id:
                external_id = create_review_id(
                    author,
                    review_date,
                    review_text,
                )

            fallback_key = (
                f"{author}|{review_date}|"
                f"{review_text[:160]}"
            )

            unique_key = external_id or fallback_key

            if unique_key in seen_keys:
                continue

            seen_keys.add(unique_key)

            reviews.append(
                {
                    "external_id": external_id,
                    "author": author,
                    "rating": rating,
                    "published_at_raw": review_date,
                    "text": review_text,
                    "possibly_collapsed": has_expand_control,
                    "source_url": page.url,
                }
            )

        except Exception as error:
            print(
                "Не удалось обработать контейнер "
                f"отзыва {index}: {error}"
            )

    return reviews, used_selector


def validate_reviews(
    reviews: list[dict[str, Any]],
) -> list[str]:
    """Проверяет качество извлечённых данных."""

    errors: list[str] = []
    known_ids: set[str] = set()

    for index, review in enumerate(
        reviews,
        start=1,
    ):
        external_id = clean_text(
            str(
                review.get(
                    "external_id",
                    "",
                )
            )
        )

        author = clean_text(
            str(
                review.get(
                    "author",
                    "",
                )
            )
        )

        review_text = clean_text(
            str(
                review.get(
                    "text",
                    "",
                )
            )
        )

        rating = int(
            review.get(
                "rating",
                0,
            )
        )

        if not external_id:
            errors.append(
                f"Отзыв {index}: отсутствует ID."
            )

        if external_id in known_ids:
            errors.append(
                f"Отзыв {index}: повторяется ID "
                f"{external_id}."
            )

        known_ids.add(external_id)

        if not author:
            errors.append(
                f"Отзыв {index}: отсутствует автор."
            )

        if not review_text:
            errors.append(
                f"Отзыв {index}: отсутствует текст."
            )

        if review_text.endswith(
            ("… ещё", "... ещё")
        ):
            errors.append(
                f"Отзыв {index}: текст сокращён."
            )

        if review.get(
            "possibly_collapsed",
            False,
        ):
            errors.append(
                f"Отзыв {index}: "
                "после извлечения остался "
                "элемент раскрытия текста."
            )

        if not 1 <= rating <= 5:
            errors.append(
                f"Отзыв {index}: "
                f"некорректная оценка {rating}."
            )

    return errors


def save_collapsed_review_diagnostics(
    page: Page,
    output_directory: Path,
) -> int:
    """Сохраняет HTML отзывов с реальным элементом раскрытия."""

    reviews = page.locator(
        ".business-review-view"
    )

    saved_count = 0

    for index in range(reviews.count()):
        container = reviews.nth(index)

        collapsed_text = container.locator(
            ".spoiler-view__text._collapsed"
        )

        expand_control = container.locator(
            ".business-review-view__expand"
        )

        if (
            not collapsed_text.count()
            or not expand_control.count()
        ):
            continue

        try:
            if not expand_control.first.is_visible():
                continue

            author = first_text(
                container,
                (
                    ".business-review-view__author",
                    "[itemprop='author']",
                    "[class*='author']",
                ),
            )

            safe_author = "".join(
                character
                if character.isalnum()
                else "-"
                for character in author
            ).strip("-")

            if not safe_author:
                safe_author = f"review-{index + 1}"

            html_path = (
                output_directory
                / (
                    "collapsed-review-"
                    f"{index + 1}-"
                    f"{safe_author}.html"
                )
            )

            text_path = (
                output_directory
                / (
                    "collapsed-review-"
                    f"{index + 1}-"
                    f"{safe_author}.txt"
                )
            )

            html_path.write_text(
                container.evaluate(
                    "(element) => element.outerHTML"
                ),
                encoding="utf-8",
            )

            text_path.write_text(
                container.inner_text(),
                encoding="utf-8",
            )

            saved_count += 1

            print(
                "Сохранён сокращённый отзыв: "
                f"{html_path}"
            )

        except Exception as error:
            print(
                "Не удалось сохранить диагностику "
                f"отзыва {index + 1}: {error}"
            )

    return saved_count


def save_review_probe(
    output_directory: Path,
    config: dict[str, Any],
    reviews: list[dict[str, Any]],
    selector: str,
    selector_counts: dict[str, int],
) -> Path:
    """Сохраняет пробный JSON с загруженными отзывами."""

    output_path = (
        output_directory
        / "reviews-probe.json"
    )

    payload = {
        "schema_version": 1,
        "business_id": config["business_id"],
        "organization_name": config[
            "organization_name"
        ],
        "source_url": config["source_url"],
        "exported_at": (
            datetime.now()
            .astimezone()
            .isoformat()
        ),
        "diagnostics": {
            "used_selector": selector,
            "selector_counts": selector_counts,
            "loaded_reviews_count": len(reviews),
        },
        "reviews": reviews,
    }

    output_path.write_text(
        json.dumps(
            payload,
            ensure_ascii=False,
            indent=2,
        ),
        encoding="utf-8",
    )

    return output_path


def save_final_export(
    output_directory: Path,
    config: dict[str, Any],
    reviews: list[dict[str, Any]],
) -> Path:
    """Сохраняет итоговый JSON для импорта в WordPress."""

    output_path = (
        output_directory
        / "wh-yandex-reviews.json"
    )

    exported_reviews: list[dict[str, Any]] = []

    for review in reviews:
        exported_reviews.append(
            {
                "external_id": clean_text(
                    str(review["external_id"])
                ),
                "author": clean_text(
                    str(review["author"])
                ),
                "rating": int(
                    review["rating"]
                ),
                "published_at_raw": clean_text(
                    str(
                        review[
                            "published_at_raw"
                        ]
                    )
                ),
                "text": clean_text(
                    str(review["text"])
                ),
                "owner_reply": "",
                "photos": [],
                "source_url": config["source_url"],
            }
        )

    payload = {
        "schema_version": 1,
        "source": "yandex_maps",
        "business_id": config["business_id"],
        "organization_name": config[
            "organization_name"
        ],
        "source_url": config["source_url"],
        "exported_at": (
            datetime.now()
            .astimezone()
            .isoformat()
        ),
        "reviews_count": len(
            exported_reviews
        ),
        "reviews": exported_reviews,
    }

    output_path.write_text(
        json.dumps(
            payload,
            ensure_ascii=False,
            indent=2,
        ),
        encoding="utf-8",
    )

    return output_path


def main() -> int:
    """Запускает диагностическую проверку."""

    try:
        config = load_config()
        output_directory = create_output_directory(
            config
        )

        print(
            "Открываю карточку организации "
            f"{config['organization_name']}..."
        )

        with sync_playwright() as playwright:
            browser = create_browser(
                playwright,
                config["headless"],
            )

            context = create_context(browser)

            try:
                page = context.new_page()

                open_organization_page(
                    page,
                    config,
                )

                result = verify_organization(
                    page,
                    config,
                )

                selector_counts = (
                    inspect_review_containers(
                        page
                    )
                )

                reviews, used_selector = (
                    extract_loaded_reviews(
                        page
                    )
                )

                collapsed_diagnostics_count = (
                    save_collapsed_review_diagnostics(
                        page,
                        output_directory,
                    )
                )

                validation_errors = (
                    validate_reviews(
                        reviews
                    )
                )

                final_export_path = None

                if not validation_errors:
                    final_export_path = (
                        save_final_export(
                            output_directory,
                            config,
                            reviews,
                        )
                    )

                probe_path = save_review_probe(
                    output_directory,
                    config,
                    reviews,
                    used_selector,
                    selector_counts,
                )

                print_result(result)

                print()
                print("Проверка контейнеров отзывов:")

                for selector, count in (
                    selector_counts.items()
                ):
                    print(
                        f"  {selector}: {count}"
                    )

                print()
                print(
                    "Использованный селектор: "
                    f"{used_selector or 'не найден'}"
                )

                print(
                    "Извлечено отзывов: "
                    f"{len(reviews)}"
                )

                print(
                    "Сохранено сокращённых контейнеров: "
                    f"{collapsed_diagnostics_count}"
                )

                print(
                    "Ошибок проверки: "
                    f"{len(validation_errors)}"
                )

                for validation_error in (
                    validation_errors[:10]
                ):
                    print(
                        f"  - {validation_error}"
                    )

                if final_export_path:
                    print()
                    print(
                        "Итоговый файл для WordPress: "
                        f"{final_export_path}"
                    )
                else:
                    print()
                    print(
                        "Итоговый файл не создан, "
                        "поскольку найдены ошибки."
                    )

                print(
                    "Пробный JSON: "
                    f"{probe_path}"
                )

                for review in reviews[:3]:
                    print()
                    print(
                        "Автор: "
                        f"{review['author']}"
                    )
                    print(
                        "Оценка: "
                        f"{review['rating']}"
                    )
                    print(
                        "Дата: "
                        f"{review['published_at_raw']}"
                    )
                    print(
                        "Текст: "
                        f"{review['text'][:180]}"
                    )

                save_diagnostics(
                    page,
                    output_directory,
                    result,
                )

                if not result["business_id_found"]:
                    print()
                    print(
                        "Ошибка: не удалось подтвердить "
                        "ID организации."
                    )
                    return 2

                if not result["organization_name_found"]:
                    print()
                    print(
                        "Предупреждение: название WH "
                        "не найдено в тексте страницы."
                    )

                print()
                print(
                    "Экспорт завершён успешно. "
                    f"Сохранено отзывов: {len(reviews)}."
                )

                return 0

            finally:
                context.close()
                browser.close()

    except FileNotFoundError as error:
        print(f"Ошибка конфигурации: {error}")
        return 1

    except json.JSONDecodeError as error:
        print(
            "Ошибка JSON в config.json: "
            f"{error}"
        )
        return 1

    except PlaywrightTimeoutError as error:
        print(
            "Страница не загрузилась за отведённое "
            f"время: {error}"
        )
        return 3

    except Exception as error:
        print(
            "Непредвиденная ошибка: "
            f"{type(error).__name__}: {error}"
        )
        return 10


if __name__ == "__main__":
    sys.exit(main())