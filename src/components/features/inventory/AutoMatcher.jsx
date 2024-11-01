import { ArrowRightCircleIcon, XCircleIcon } from "@heroicons/react/24/outline";
import apiFetch from "@wordpress/api-fetch";
import { useState } from "@wordpress/element";
import React from "react";

function AutoMatcher({ setIsAutoMatchOpen, inventory }) {
  const [selectValue, setSelectValue] = useState("sku");
  const [loading, setLoading] = useState(false);
  const [successMessage, setSuccessMessage] = useState("");

  // Function to split inventory data into batches
  const splitIntoBatches = (data, batchSize) => {
    const batches = [];
    for (let i = 0; i < data.length; i += batchSize) {
      batches.push(data.slice(i, i + batchSize));
    }
    return batches;
  };

  // Function to send a batch to the backend
  const sendBatch = async (batch, matchKey) => {
    try {
      const response = await apiFetch({
        path: "/sws/v1/matcher",
        method: "POST",
        data: { match_key: matchKey, inventory: batch },
      });
      console.log(response);
      console.log("Batch sent successfully");
    } catch (error) {
      console.error("Error sending batch:", error);
    }
  };

  // Function to handle the "Start Matching" click
  const handleStartMatching = async () => {
    setLoading(true);
    const batchSize = 100; // Adjust batch size as needed
    const batches = splitIntoBatches(inventory, batchSize);
    for (const batch of batches) {
      await sendBatch(batch, selectValue);
    }
    setSuccessMessage(
      "Auto matcher complete, reload inventory table to see results"
    );
    setLoading(false);
  };

  return (
    <div className="w-[40vw] max-w-[40vw] mx-auto bg-white p-6 rounded-xl">
      <div className="w-full">
        <header className="flex justify-between items-center gap-2 mb-4">
          <h3 className="text-lg font-medium leading-6 text-gray-900">
            Auto Matcher
          </h3>
        </header>
      </div>
      <p>
        Use the auto matcher to link existing WooCommerce products with Square
        products via SKU. Already matched products will be ignored.
      </p>
      <p className="text-sm font-semibold mt-3">Match via:</p>
      <select
        className="block !rounded-lg !border-0 !py-1.5 text-gray-900 !ring-1 !ring-inset !ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-sky-600 sm:text-sm !px-4 !leading-6 mt-2 !pr-10"
        value={selectValue}
        onChange={(e) => setSelectValue(e.target.value)}
      >
        <option value="sku">SKU</option>
      </select>
      {successMessage && <p className="text-sky-500 mt-4">{successMessage}</p>}
      {!loading ? (
        <div className="flex items-center mt-10 justify-end gap-2">
          <button
            type="button"
            onClick={() => setIsAutoMatchOpen(false)}
            className="relative inline-flex items-center rounded-md bg-gray-400 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-sky-400"
          >
            <span>Close</span>
            <XCircleIcon
              className="ml-1.5 h-4 w-4 text-white"
              aria-hidden="true"
            />
          </button>
          <button
            type="button"
            onClick={handleStartMatching}
            className="relative inline-flex items-center rounded-md bg-sky-500 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-sky-400"
          >
            <span>Start matching</span>
            <ArrowRightCircleIcon
              className="ml-1.5 h-4 w-4 text-white"
              aria-hidden="true"
            />
          </button>
        </div>
      ) : (
        <div className="flex items-center mt-10 justify-end gap-2">
          <button
            type="button"
            className="relative inline-flex items-center rounded-md bg-sky-500 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-sky-400"
            disabled=""
          >
            <svg
              class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                strokeWidth="4"
              ></circle>
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              ></path>
            </svg>
            Processing...
          </button>
        </div>
      )}
    </div>
  );
}

export default AutoMatcher;
