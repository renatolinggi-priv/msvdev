<?php
// jmdefinition_tw.php - Tailwind Version
// WICHTIG: Diese Zeile aktiviert Tailwind CSS
$page_uses_tailwind = true;

include '../dbconnect.inc.php';
include '../header.inc.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!-- Alpine.js App für Interaktivität -->
<div x-data="jmDefinitionApp()" x-init="init()" class="tailwind-module">
    <!-- Container -->
    <div class="tw-min-h-screen tw-bg-gray-50">
        <div class="tw-container tw-mx-auto tw-px-4 tw-py-6">
            
            <!-- Header -->
            <div class="tw-mb-6 tw-animate-fade-in">
                <h1 class="tw-text-3xl tw-font-bold tw-text-gray-800 tw-flex tw-items-center">
                    <svg class="tw-w-8 tw-h-8 tw-mr-3 tw-text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    Jahresmeisterschaft Definition
                </h1>
                <p class="tw-text-gray-600 tw-mt-2">Verwalte die Anlässe und Definitionen der Jahresmeisterschaft</p>
            </div>

            <!-- Jahr-Auswahl Card -->
            <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6 tw-mb-6">
                <div class="tw-flex tw-flex-col md:tw-flex-row tw-items-start md:tw-items-center tw-gap-4">
                    <div class="tw-flex-1">
                        <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-2">
                            <svg class="tw-inline tw-w-4 tw-h-4 tw-mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Jahr auswählen
                        </label>
                        <select x-model="selectedYear" @change="loadData()" 
                                class="tw-w-full md:tw-w-64 tw-px-4 tw-py-2 tw-border tw-border-gray-300 tw-rounded-lg focus:tw-ring-2 focus:tw-ring-primary focus:tw-border-transparent">
                            <template x-for="year in years" :key="year">
                                <option :value="year" x-text="year"></option>
                            </template>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6 tw-mb-6">
                <div class="tw-flex tw-flex-col lg:tw-flex-row tw-justify-between tw-items-start lg:tw-items-center tw-gap-4">
                    <!-- Buttons -->
                    <div class="tw-flex tw-flex-wrap tw-gap-3">
                        <button @click="openNewModal()" 
                                class="tw-px-4 tw-py-2 tw-bg-success tw-text-white tw-rounded-lg hover:tw-bg-green-600 focus:tw-ring-4 focus:tw-ring-green-200 tw-flex tw-items-center tw-transition-all">
                            <svg class="tw-w-5 tw-h-5 tw-mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Neuer Anlass
                        </button>
                        
                        <button @click="saveAll()" 
                                class="tw-px-4 tw-py-2 tw-bg-primary tw-text-white tw-rounded-lg hover:tw-bg-blue-700 focus:tw-ring-4 focus:tw-ring-blue-200 tw-flex tw-items-center tw-transition-all">
                            <svg class="tw-w-5 tw-h-5 tw-mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V2"></path>
                            </svg>
                            Speichern
                        </button>

                        <!-- Export Buttons -->
                        <div class="tw-flex tw-gap-2">
                            <button @click="exportPDF()" 
                                    class="tw-px-3 tw-py-2 tw-bg-gray-100 tw-text-gray-700 tw-rounded-lg hover:tw-bg-gray-200 tw-flex tw-items-center tw-text-sm tw-transition-all">
                                <svg class="tw-w-4 tw-h-4 tw-mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                                PDF
                            </button>
                            
                            <button @click="exportWord()" 
                                    class="tw-px-3 tw-py-2 tw-bg-gray-100 tw-text-gray-700 tw-rounded-lg hover:tw-bg-gray-200 tw-flex tw-items-center tw-text-sm tw-transition-all">
                                <svg class="tw-w-4 tw-h-4 tw-mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Word
                            </button>
                            
                            <button @click="exportICS()" 
                                    class="tw-px-3 tw-py-2 tw-bg-gray-100 tw-text-gray-700 tw-rounded-lg hover:tw-bg-gray-200 tw-flex tw-items-center tw-text-sm tw-transition-all">
                                <svg class="tw-w-4 tw-h-4 tw-mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                ICS
                            </button>
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <div x-show="lastSaved" class="tw-text-sm tw-text-gray-500">
                        Zuletzt gespeichert: <span x-text="lastSaved"></span>
                    </div>
                </div>
            </div>

            <!-- Haupttabelle -->
            <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-overflow-hidden tw-mb-6">
                <div class="tw-bg-gradient-to-r tw-from-gray-50 tw-to-gray-100 tw-px-6 tw-py-4 tw-border-b tw-border-gray-200">
                    <h2 class="tw-text-lg tw-font-semibold tw-text-gray-800 tw-flex tw-items-center">
                        <svg class="tw-w-5 tw-h-5 tw-mr-2 tw-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Anlässe verwalten
                    </h2>
                </div>
                
                <!-- Tabelle mit horizontalem Scrolling -->
                <div class="tw-overflow-x-auto">
                    <table class="tw-w-full tw-min-w-[1200px]">
                        <thead class="tw-bg-secondary tw-text-white tw-sticky tw-top-0 tw-z-10">
                            <tr>
                                <th class="tw-px-4 tw-py-3 tw-text-left tw-text-xs tw-font-medium tw-uppercase tw-tracking-wider tw-w-20">
                                    <span class="tw-flex tw-items-center">
                                        <svg class="tw-w-4 tw-h-4 tw-mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                        </svg>
                                        Nr.
                                    </span>
                                </th>
                                <th class="tw-px-4 tw-py-3 tw-text-left tw-text-xs tw-font-medium tw-uppercase tw-tracking-wider">Bezeichnung</th>
                                <th class="tw-px-4 tw-py-3 tw-text-left tw-text-xs tw-font-medium tw-uppercase tw-tracking-wider">Adresse</th>
                                <th class="tw-px-4 tw-py-3 tw-text-left tw-text-xs tw-font-medium tw-uppercase tw-tracking-wider">Schiesstage</th>
                                <th class="tw-px-4 tw-py-3 tw-text-center tw-text-xs tw-font-medium tw-uppercase tw-tracking-wider tw-w-24">Max</th>
                                <th class="tw-px-4 tw-py-3 tw-text-center tw-text-xs tw-font-medium tw-uppercase tw-tracking-wider tw-w-24">Zuschlag</th>
                                <th class="tw-px-4 tw-py-3 tw-text-center tw-text-xs tw-font-medium tw-uppercase tw-tracking-wider tw-w-20" title="Streicher">
                                    <svg class="tw-w-4 tw-h-4 tw-mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                    </svg>
                                </th>
                                <th class="tw-px-4 tw-py-3 tw-text-center tw-text-xs tw-font-medium tw-uppercase tw-tracking-wider tw-w-20" title="Erweitert">
                                    <svg class="tw-w-4 tw-h-4 tw-mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                </th>
                                <th class="tw-px-4 tw-py-3 tw-text-center tw-text-xs tw-font-medium tw-uppercase tw-tracking-wider tw-w-20" title="Info">
                                    <svg class="tw-w-4 tw-h-4 tw-mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </th>
                                <th class="tw-px-4 tw-py-3 tw-text-center tw-text-xs tw-font-medium tw-uppercase tw-tracking-wider tw-w-20" title="Gruppe">
                                    <svg class="tw-w-4 tw-h-4 tw-mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </th>
                                <th class="tw-px-4 tw-py-3 tw-text-center tw-text-xs tw-font-medium tw-uppercase tw-tracking-wider tw-w-24">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="tw-bg-white tw-divide-y tw-divide-gray-200">
                            <template x-for="(item, index) in items" :key="item.id">
                                <tr class="hover:tw-bg-gray-50 tw-transition-colors" :data-id="item.id">
                                    <!-- Nr. mit Drag Handle -->
                                    <td class="tw-px-4 tw-py-2 tw-whitespace-nowrap">
                                        <div class="tw-flex tw-items-center">
                                            <span class="drag-handle tw-cursor-move tw-text-gray-400 hover:tw-text-gray-600 tw-mr-2">
                                                ⋮⋮
                                            </span>
                                            <span class="tw-font-semibold tw-text-primary" x-text="item.row_num"></span>
                                        </div>
                                    </td>
                                    
                                    <!-- Bezeichnung -->
                                    <td class="tw-px-4 tw-py-2">
                                        <textarea x-model="item.bezeichnung" 
                                                  class="tw-w-full tw-px-2 tw-py-1 tw-text-sm tw-border tw-border-gray-300 tw-rounded focus:tw-ring-1 focus:tw-ring-primary focus:tw-border-primary tw-resize-none"
                                                  rows="2"></textarea>
                                    </td>
                                    
                                    <!-- Adresse -->
                                    <td class="tw-px-4 tw-py-2">
                                        <textarea x-model="item.adresse" 
                                                  class="tw-w-full tw-px-2 tw-py-1 tw-text-sm tw-border tw-border-gray-300 tw-rounded focus:tw-ring-1 focus:tw-ring-primary focus:tw-border-primary tw-resize-none"
                                                  rows="2"></textarea>
                                    </td>
                                    
                                    <!-- Schiesstage -->
                                    <td class="tw-px-4 tw-py-2">
                                        <textarea x-model="item.schiesstage" 
                                                  class="tw-w-full tw-px-2 tw-py-1 tw-text-sm tw-border tw-border-gray-300 tw-rounded focus:tw-ring-1 focus:tw-ring-primary focus:tw-border-primary tw-resize-none"
                                                  rows="2"></textarea>
                                    </td>
                                    
                                    <!-- Max -->
                                    <td class="tw-px-4 tw-py-2 tw-text-center">
                                        <input type="number" x-model="item.maxpunkte" 
                                               class="tw-w-20 tw-px-2 tw-py-1 tw-text-sm tw-text-center tw-border tw-border-gray-300 tw-rounded focus:tw-ring-1 focus:tw-ring-primary focus:tw-border-primary">
                                    </td>
                                    
                                    <!-- Zuschlag -->
                                    <td class="tw-px-4 tw-py-2 tw-text-center">
                                        <input type="number" x-model="item.zuschlag" 
                                               class="tw-w-20 tw-px-2 tw-py-1 tw-text-sm tw-text-center tw-border tw-border-gray-300 tw-rounded focus:tw-ring-1 focus:tw-ring-primary focus:tw-border-primary">
                                    </td>
                                    
                                    <!-- Checkboxen -->
                                    <td class="tw-px-4 tw-py-2 tw-text-center">
                                        <input type="checkbox" x-model="item.streicher" 
                                               class="tw-w-4 tw-h-4 tw-text-primary tw-border-gray-300 tw-rounded focus:tw-ring-primary">
                                    </td>
                                    <td class="tw-px-4 tw-py-2 tw-text-center">
                                        <input type="checkbox" x-model="item.erweitert" 
                                               class="tw-w-4 tw-h-4 tw-text-primary tw-border-gray-300 tw-rounded focus:tw-ring-primary">
                                    </td>
                                    <td class="tw-px-4 tw-py-2 tw-text-center">
                                        <input type="checkbox" x-model="item.info" 
                                               class="tw-w-4 tw-h-4 tw-text-primary tw-border-gray-300 tw-rounded focus:tw-ring-primary">
                                    </td>
                                    <td class="tw-px-4 tw-py-2 tw-text-center">
                                        <input type="checkbox" x-model="item.gruppe" 
                                               class="tw-w-4 tw-h-4 tw-text-primary tw-border-gray-300 tw-rounded focus:tw-ring-primary">
                                    </td>
                                    
                                    <!-- Aktionen -->
                                    <td class="tw-px-4 tw-py-2 tw-text-center">
                                        <button @click="deleteItem(item.id)" 
                                                class="tw-p-1 tw-text-danger hover:tw-bg-red-50 tw-rounded tw-transition-colors">
                                            <svg class="tw-w-5 tw-h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            
                            <!-- Loading State -->
                            <tr x-show="loading">
                                <td colspan="11" class="tw-px-4 tw-py-8 tw-text-center">
                                    <div class="tw-flex tw-justify-center tw-items-center">
                                        <svg class="tw-animate-spin tw-h-8 tw-w-8 tw-text-primary" fill="none" viewBox="0 0 24 24">
                                            <circle class="tw-opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="tw-opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="tw-ml-2">Lade Daten...</span>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Empty State -->
                            <tr x-show="!loading && items.length === 0">
                                <td colspan="11" class="tw-px-4 tw-py-8 tw-text-center tw-text-gray-500">
                                    <svg class="tw-w-12 tw-h-12 tw-mx-auto tw-mb-4 tw-text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                    </svg>
                                    Keine Anlässe vorhanden
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Zusatztext -->
            <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6">
                <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-2">
                    <svg class="tw-inline tw-w-4 tw-h-4 tw-mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Infotext zur Jahresmeisterschaft
                </label>
                <textarea x-model="zusatztext" 
                          class="tw-w-full tw-px-3 tw-py-2 tw-border tw-border-gray-300 tw-rounded-lg focus:tw-ring-2 focus:tw-ring-primary focus:tw-border-transparent"
                          rows="5"
                          placeholder="Hier können zusätzliche Informationen zur Jahresmeisterschaft eingegeben werden..."></textarea>
            </div>
        </div>
    </div>

    <!-- Modal für neuen Anlass -->
    <div x-show="showModal" 
         x-transition:enter="tw-transition tw-ease-out tw-duration-300"
         x-transition:enter-start="tw-opacity-0"
         x-transition:enter-end="tw-opacity-100"
         x-transition:leave="tw-transition tw-ease-in tw-duration-200"
         x-transition:leave-start="tw-opacity-100"
         x-transition:leave-end="tw-opacity-0"
         class="tw-fixed tw-inset-0 tw-bg-black tw-bg-opacity-50 tw-z-50 tw-flex tw-items-center tw-justify-center tw-p-4"
         style="display: none;">
        
        <div @click.outside="showModal = false" 
             x-transition:enter="tw-transition tw-ease-out tw-duration-300"
             x-transition:enter-start="tw-opacity-0 tw-transform tw-scale-90"
             x-transition:enter-end="tw-opacity-100 tw-transform tw-scale-100"
             x-transition:leave="tw-transition tw-ease-in tw-duration-200"
             x-transition:leave-start="tw-opacity-100 tw-transform tw-scale-100"
             x-transition:leave-end="tw-opacity-0 tw-transform tw-scale-90"
             class="tw-bg-white tw-rounded-2xl tw-shadow-xl tw-max-w-2xl tw-w-full tw-max-h-[90vh] tw-overflow-hidden">
            
            <!-- Modal Header -->
            <div class="tw-bg-primary tw-text-white tw-px-6 tw-py-4">
                <div class="tw-flex tw-justify-between tw-items-center">
                    <h3 class="tw-text-xl tw-font-semibold tw-flex tw-items-center">
                        <svg class="tw-w-6 tw-h-6 tw-mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Neuen Anlass hinzufügen
                    </h3>
                    <button @click="showModal = false" class="tw-text-white/80 hover:tw-text-white">
                        <svg class="tw-w-6 tw-h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Modal Body -->
            <div class="tw-p-6 tw-overflow-y-auto tw-max-h-[60vh]">
                <div class="tw-space-y-4">
                    <div>
                        <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Anlassname *</label>
                        <textarea x-model="newItem.bezeichnung" 
                                  class="tw-w-full tw-px-3 tw-py-2 tw-border tw-border-gray-300 tw-rounded-lg focus:tw-ring-2 focus:tw-ring-primary focus:tw-border-transparent"
                                  rows="3"
                                  placeholder="z.B. Hanslin Gedenk Schiessen"></textarea>
                    </div>
                    
                    <div>
                        <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Adresse</label>
                        <textarea x-model="newItem.adresse" 
                                  class="tw-w-full tw-px-3 tw-py-2 tw-border tw-border-gray-300 tw-rounded-lg focus:tw-ring-2 focus:tw-ring-primary focus:tw-border-transparent"
                                  rows="3"
                                  placeholder="Schützenhaus XY&#10;Musterstrasse 1&#10;8000 Zürich"></textarea>
                    </div>
                    
                    <div>
                        <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Schiesstage</label>
                        <textarea x-model="newItem.schiesstage" 
                                  class="tw-w-full tw-px-3 tw-py-2 tw-border tw-border-gray-300 tw-rounded-lg focus:tw-ring-2 focus:tw-ring-primary focus:tw-border-transparent"
                                  rows="3"
                                  placeholder="Freitag 14. März 2025 14:00 – 17:00 Uhr&#10;Samstag 15. März 2025 08:00 – 12:00 Uhr"></textarea>
                    </div>
                    
                    <div class="tw-grid tw-grid-cols-2 tw-gap-4">
                        <div>
                            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Maximalpunkte</label>
                            <input type="number" x-model="newItem.maxpunkte" 
                                   class="tw-w-full tw-px-3 tw-py-2 tw-border tw-border-gray-300 tw-rounded-lg focus:tw-ring-2 focus:tw-ring-primary focus:tw-border-transparent"
                                   placeholder="100">
                        </div>
                        <div>
                            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Zuschlag</label>
                            <input type="number" x-model="newItem.zuschlag" 
                                   class="tw-w-full tw-px-3 tw-py-2 tw-border tw-border-gray-300 tw-rounded-lg focus:tw-ring-2 focus:tw-ring-primary focus:tw-border-transparent"
                                   placeholder="0">
                        </div>
                    </div>
                    
                    <div class="tw-grid tw-grid-cols-2 tw-gap-4">
                        <div class="tw-space-y-2">
                            <label class="tw-flex tw-items-center">
                                <input type="checkbox" x-model="newItem.streicher" 
                                       class="tw-w-4 tw-h-4 tw-text-primary tw-border-gray-300 tw-rounded focus:tw-ring-primary tw-mr-2">
                                <span class="tw-text-sm">Streicher</span>
                            </label>
                            <label class="tw-flex tw-items-center">
                                <input type="checkbox" x-model="newItem.erweitert" 
                                       class="tw-w-4 tw-h-4 tw-text-primary tw-border-gray-300 tw-rounded focus:tw-ring-primary tw-mr-2">
                                <span class="tw-text-sm">Erweiterte JM</span>
                            </label>
                        </div>
                        <div class="tw-space-y-2">
                            <label class="tw-flex tw-items-center">
                                <input type="checkbox" x-model="newItem.info" 
                                       class="tw-w-4 tw-h-4 tw-text-primary tw-border-gray-300 tw-rounded focus:tw-ring-primary tw-mr-2">
                                <span class="tw-text-sm">Info</span>
                            </label>
                            <label class="tw-flex tw-items-center">
                                <input type="checkbox" x-model="newItem.gruppe" 
                                       class="tw-w-4 tw-h-4 tw-text-primary tw-border-gray-300 tw-rounded focus:tw-ring-primary tw-mr-2">
                                <span class="tw-text-sm">Gruppenwettkampf</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="tw-bg-gray-50 tw-px-6 tw-py-4 tw-flex tw-justify-between tw-items-center tw-border-t">
                <button @click="showModal = false" 
                        class="tw-px-4 tw-py-2 tw-text-gray-700 tw-bg-white tw-border tw-border-gray-300 tw-rounded-lg hover:tw-bg-gray-50 focus:tw-ring-4 focus:tw-ring-gray-200 tw-flex tw-items-center">
                    <svg class="tw-w-5 tw-h-5 tw-mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Abbrechen
                </button>
                <button @click="addNewItem()" 
                        class="tw-px-4 tw-py-2 tw-bg-success tw-text-white tw-rounded-lg hover:tw-bg-green-600 focus:tw-ring-4 focus:tw-ring-green-200 tw-flex tw-items-center">
                    <svg class="tw-w-5 tw-h-5 tw-mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Hinzufügen
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="tw-fixed tw-top-20 tw-right-4 tw-z-50 tw-space-y-2"></div>
</div>

<!-- Alpine.js App Script -->
<script>
function jmDefinitionApp() {
    return {
        // Data
        items: [],
        loading: false,
        showModal: false,
        selectedYear: new Date().getFullYear(),
        years: [],
        zusatztext: '',
        lastSaved: null,
        csrfToken: '<?php echo $_SESSION['csrf_token']; ?>',
        
        // New Item Template
        newItem: {
            bezeichnung: '',
            adresse: '',
            schiesstage: '',
            maxpunkte: '',
            zuschlag: 0,
            streicher: false,
            erweitert: false,
            info: false,
            gruppe: false
        },
        
        // Initialize
        init() {
            // Generate years
            const currentYear = new Date().getFullYear();
            for (let y = 2024; y <= currentYear; y++) {
                this.years.push(y);
            }
            
            // Load initial data
            this.loadData();
            
            // Initialize Sortable
            this.$nextTick(() => {
                this.initSortable();
            });
        },
        
        // Load data
        async loadData() {
            this.loading = true;
            
            // Simulate loading - Replace with actual AJAX call
            setTimeout(() => {
                this.items = [
                    {
                        id: 1,
                        row_num: 1,
                        bezeichnung: 'Feldschiessen',
                        adresse: 'Schützenhaus Wilen\nHauptstrasse 12\n8196 Wilen',
                        schiesstage: 'Freitag 14. Mai 2025 17:00-20:00\nSamstag 15. Mai 2025 08:00-12:00',
                        maxpunkte: 100,
                        zuschlag: 5,
                        streicher: false,
                        erweitert: false,
                        info: true,
                        gruppe: false
                    },
                    {
                        id: 2,
                        row_num: 2,
                        bezeichnung: 'Vereinsmeisterschaft',
                        adresse: 'Schützenhaus Wilen\nHauptstrasse 12\n8196 Wilen',
                        schiesstage: 'Samstag 21. Juni 2025 09:00-16:00',
                        maxpunkte: 100,
                        zuschlag: 0,
                        streicher: false,
                        erweitert: true,
                        info: false,
                        gruppe: true
                    }
                ];
                this.loading = false;
            }, 500);
            
            // Load zusatztext
            this.zusatztext = 'Beispiel Zusatztext für die Jahresmeisterschaft';
        },
        
        // Initialize Sortable
        initSortable() {
            const tbody = document.querySelector('tbody');
            if (tbody && typeof Sortable !== 'undefined') {
                new Sortable(tbody, {
                    handle: '.drag-handle',
                    animation: 150,
                    onEnd: (evt) => {
                        // Update row numbers
                        this.updateRowNumbers();
                    }
                });
            }
        },
        
        // Update row numbers
        updateRowNumbers() {
            this.items.forEach((item, index) => {
                item.row_num = index + 1;
            });
        },
        
        // Open new modal
        openNewModal() {
            this.newItem = {
                bezeichnung: '',
                adresse: '',
                schiesstage: '',
                maxpunkte: '',
                zuschlag: 0,
                streicher: false,
                erweitert: false,
                info: false,
                gruppe: false
            };
            this.showModal = true;
        },
        
        // Add new item
        addNewItem() {
            if (!this.newItem.bezeichnung) {
                this.showToast('Bitte Anlassname eingeben', 'warning');
                return;
            }
            
            const newId = Math.max(...this.items.map(i => i.id), 0) + 1;
            this.items.push({
                id: newId,
                row_num: this.items.length + 1,
                ...this.newItem
            });
            
            this.showModal = false;
            this.showToast('Anlass erfolgreich hinzugefügt', 'success');
        },
        
        // Delete item
        deleteItem(id) {
            if (confirm('Möchten Sie diesen Eintrag wirklich löschen?')) {
                this.items = this.items.filter(i => i.id !== id);
                this.updateRowNumbers();
                this.showToast('Eintrag gelöscht', 'success');
            }
        },
        
        // Save all
        saveAll() {
            this.showToast('Speichere Änderungen...', 'info');
            
            // Simulate save - Replace with actual AJAX
            setTimeout(() => {
                this.lastSaved = new Date().toLocaleTimeString('de-CH');
                this.showToast('Alle Änderungen gespeichert!', 'success');
            }, 1000);
        },
        
        // Export functions
        exportPDF() {
            this.showToast('PDF wird generiert...', 'info');
            // Add actual export logic
        },
        
        exportWord() {
            this.showToast('Word-Dokument wird generiert...', 'info');
            // Add actual export logic
        },
        
        exportICS() {
            this.showToast('ICS-Datei wird generiert...', 'info');
            // Add actual export logic
        },
        
        // Toast notification
        showToast(message, type = 'info') {
            const colors = {
                success: 'tw-bg-success',
                error: 'tw-bg-danger',
                warning: 'tw-bg-warning',
                info: 'tw-bg-info'
            };
            
            const toast = document.createElement('div');
            toast.className = `${colors[type]} tw-text-white tw-px-4 tw-py-3 tw-rounded-lg tw-shadow-lg tw-flex tw-items-center tw-space-x-2 tw-animate-slide-in`;
            toast.innerHTML = `
                <svg class="tw-w-5 tw-h-5 tw-flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    ${type === 'success' ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>' : 
                      type === 'error' ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>' :
                      type === 'warning' ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>' :
                      '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'}
                </svg>
                <span>${message}</span>
            `;
            
            document.getElementById('toast-container').appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    }
}
</script>

<?php include '../footer.inc.php'; ?>
