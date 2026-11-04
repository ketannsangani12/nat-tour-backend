<div class="modal fade" id="calendarModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12">
                                <h2 class="model-title">Please select your tour date and time:</h2>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex">
                                    <div class="calendar-wrap">
                                        <div class="calendar">
                                            <div class="calendar-header">
                                                <button id="prevButton"><svg width="9" height="14" viewBox="0 0 9 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M7.5 12L2.5 6.73684L7.5 2" stroke="#2F9DD5" stroke-width="2.5" stroke-linecap="round" />
                                                    </svg></button>
                                                <div>
                                                    <span id="calendarMonth"></span>
                                                    <span id="calendarYear"></span>
                                                </div>
                                                <button id="nextButton"><svg width="9" height="14" viewBox="0 0 9 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M1.5 12L6.5 6.73684L1.5 2" stroke="#2F9DD5" stroke-width="2.5" stroke-linecap="round" />
                                                    </svg></button>
                                            </div>

                                            <div class="weekdays">
                                                <span>Sun</span>
                                                <span>Mon</span>
                                                <span>Tue</span>
                                                <span>Wed</span>
                                                <span>Thu</span>
                                                <span>Fri</span>
                                                <span>Sat</span>
                                            </div>

                                            <div id="dates" class="dates"></div>

                                            <div id="monthSelection" class="selection hidden">
                                                <div class="month-grid" id="monthGrid"></div>
                                            </div>

                                            <div id="yearSelection" class="selection hidden">
                                                <div class="year-grid" id="yearGrid"></div>
                                            </div>

                                        </div>
                                    </div>
                                    <div class="time-slot-wrap">
                                        <div class="time-slot" id="time-slots">

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 text-end">

                                <a href="javascript:void(0);" class="continue-btn d-flex align-items-center" id="continue-btn">Continue <svg width="9" height="14" viewBox="0 0 9 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M2 12L7 6.73684L2 2" stroke="white" stroke-width="2.5" stroke-linecap="round" />
                                    </svg></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>