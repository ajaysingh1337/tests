s
<template>
    <app-layout title="Home" :showLoader="showLoader">
        <template #header>
            <!-- Page Heading -->
            <!-- <div class="page-heading d-flex py-3 bg-white shadow-sm border-bottomF">
      <div class="container">
        <h2 class="h4 font-weight-bold">Dashboard</h2>
      </div>
      </div>-->

            <section class="hero-carousel position-relative overflow-hidden">
                <Carousel :settings="settings" :breakpoints="breakpoints">
                    <Slide class="" key="1">
                        <img
                            alt="Image"
                            class="img-fluid main-image"
                            src="@/images/home/hero-bg.png"
                        />
                        <div class="overlay"></div>

                        <div class="position-absolute top-50">
                            <div
                                class="d-flex pt-3 pt-md-0 flex-column mx-3 mx-md-0"
                            >
                                <div
                                    v-if="
                                        getPageContentType(
                                            'main_heading_slide_1'
                                        ) == 'textarea'
                                    "
                                    class="text-start text-md-center"
                                >
                                    <div
                                        class="text-white"
                                        v-html="
                                            getPageContent(
                                                'main_heading_slide_1'
                                            )
                                        "
                                    ></div>
                                    <div class="text-md-center">
                                        <h5 class="text-white fw-medium">
                                            {{
                                                $page.props.dashboard_data
                                                    .total_teachers
                                            }}
                                            {{ __("online tutors") }}
                                        </h5>
                                    </div>
                                </div>
                                <div
                                    v-else-if="
                                        getPageContentType(
                                            'main_heading_slide_1'
                                        ) == 'text'
                                    "
                                >
                                    <p>
                                        {{
                                            getPageContent(
                                                "main_heading_slide_1"
                                            ) ?? "-"
                                        }}
                                    </p>
                                </div>
                                <div v-else>
                                    <h1 class="text-white display-6">
                                        <small
                                            class="d-block fs-2 text-capitalize fw-bold text-black"
                                            >{{
                                                __(
                                                    "Your Legal Solution Just A Click Away"
                                                )
                                            }}</small
                                        >
                                        {{ __("Online Consultation") }}
                                    </h1>
                                    <p class="text-white py-3 fs-6">
                                        <span class="fw-bold">
                                            <span class="text-primary"
                                                >{{ __("27/7 Video") }}
                                            </span>
                                            {{ __("Consultations, ") }}
                                            <span class="text-primary"
                                                >{{ __("Audio") }}
                                            </span>

                                            {{ __("Calls + ") }}

                                            <span class="text-primary"
                                                >{{ __("Chat") }}
                                            </span>

                                            {{ __("Consultation") }}
                                        </span>
                                    </p>
                                </div>
                                <FindTeacherBar :home="true"></FindTeacherBar>
                                <div
                                    v-if="
                                        getPageContentType(
                                            'header_content_slide_1'
                                        ) == 'textarea'
                                    "
                                >
                                    <div
                                        class="text-white"
                                        v-html="
                                            getPageContent(
                                                'header_content_slide_1'
                                            )
                                        "
                                    ></div>
                                </div>
                                <div
                                    v-else-if="
                                        getPageContentType(
                                            'header_content_slide_1'
                                        ) == 'text'
                                    "
                                >
                                    <p>
                                        {{
                                            getPageContent(
                                                "header_content_slide_1"
                                            ) ?? "-"
                                        }}
                                    </p>
                                </div>
                                <p
                                    v-else
                                    class="mb-0 text-primary mt-3 pe-md-4"
                                >
                                    Get professional legal advice from the
                                    comfort of your home with our virtual
                                    consultations. Connect with experienced
                                    teachers online and find the answers you
                                    need for your legal matters. Convenient,
                                    confidential, and just a click away
                                </p>
                            </div>
                        </div>
                    </Slide>

                    <!-- <template #addons> -->
                    <!-- <Navigation /> -->
                    <!-- <Pagination class="mt-0 my-5" />
          </template> -->
                </Carousel>
            </section>
        </template>
        <home-statistics-bar></home-statistics-bar>
        <home-static-cards-section></home-static-cards-section>
        <spotlight-teacher-section></spotlight-teacher-section>
        <teachers-tabs-section></teachers-tabs-section>
        <PopularCoursesSection></PopularCoursesSection>
        <featured-academy-section></featured-academy-section>

        <!-- Quick Buy Services -->

        <div class="p-6 bg-white">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="text-center">
                            <h1 class="text-primary">
                                {{ "Quick Buy Services" }}
                            </h1>
                            <p class="mx-5">
                                Streamline your learning with our easy-to-access
                                services. Whether you're looking for a one-time
                                session or a package of lessons, purchasing is
                                quick and simple. Get the help you need, when
                                you need it, with just a few clicks.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="row py-4">
                    <!-- Loop through service categories -->
                    <div
                        class="col-md-4"
                        v-for="(category, index) in serviceCategories"
                        :key="category.id"
                    >
                        <div
                            v-if="index <= 2"
                            class="card border-0 mx-2 mb-3 mb-md-0"
                        >
                            <div
                                class="rounded-5 overflow-hidden image-container"
                            >
                                <img
                                    v-if="category.image"
                                    class="image"
                                    :src="category.image"
                                    alt="categoryimg"
                                />
                                <img
                                    v-else
                                    class="image"
                                    src=""
                                    alt="categoryimg"
                                />
                            </div>
                            <div class="card-body">
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <h3 class="fs-2 fw-bolder mb-0">
                                        {{ category.name }}
                                    </h3>
                                    <star-rating
                                        :rating="category.rating"
                                        :star-size="18"
                                        :read-only="true"
                                        :increment="0.01"
                                        :show-rating="false"
                                    ></star-rating>
                                </div>
                                <p
                                    class="fs-4 mt-3 fw-regular text-secondary lineclamp-2"
                                    v-html="category.description"
                                ></p>
                                <Link
                                    :href="
                                        route('services.listing', {
                                            service_category: category.slug,
                                        })
                                    "
                                    class="btn btn-primary px-5 rounded-3 fw-bolder"
                                    >{{ __("Explore") }}</Link
                                >
                            </div>
                        </div>
                    </div>
                    <!-- End loop -->
                </div>
                <div class="row pt-4 justify-content-center">
                    <div class="col-md-3 d-flex justify-content-center">
                        <Link
                            :href="route('services.listing')"
                            class="btn btn-primary rounded-pill"
                        >
                            <span class="button-text">{{
                                getPageContent("general_view_more_btn_text") ??
                                __("view more")
                            }}</span>
                        </Link>
                        <!-- <Link :href="route('law_firms.listing')" class="btn rounded-5 border-0 px-4 text-white btn-primary">{{ __('view more') }}</Link> -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Buy Services -->

        <!-- Free Online Consultation -->

        <featured-event-section findTeachers="true"></featured-event-section>

        <!-- Find Nearest Teacher -->
        <!-- <div class="section nearest-teacher mt-5 pt-5">
      <div class="container">
        <div class="row py-md-5 align-items-center">
          <div class="col-lg-6 mb-4 mb-lg-0 text-center">
            <img
              class="img-fluid"
              width="350"
              src="@/images/home/group3.png"
              alt="Image"
            />
          </div>

          <div class="col-lg-6">
            <div
              v-if="
                getPageContentType('find_nearest_teacher_description') ==
                'textarea'
              "
            >
              <div
                v-html="getPageContent('find_nearest_teacher_description')"
              ></div>
            </div>
            <div
              v-else-if="
                getPageContentType('find_nearest_teacher_description') == 'text'
              "
            >
              <p>
                {{ getPageContent("find_nearest_teacher_description") ?? "-" }}
              </p>
            </div>
            <div v-else>
              <span class="fs-3">{{ __("Find Your") }}</span>
              <h2 class="display-6">
                {{ __("Nearest Teachers") }}
              </h2>

              <p>
                {{ __("resonance healing energy description") }}
              </p>
            </div>
            <div>
              <a :href="route('teachers.listing')" class="btn btn-primary">{{
                getPageContent("find_nearest_teacher_button_text") ??
                __("Find Nearest Teachers")
              }}</a>
            </div>
          </div>
        </div>
      </div>
    </div> -->

        <!-- Free Online Consultation -->
        <!-- <div class="free-consulation pt-4">
      <div class="container">
        <div class="row">
          <div class="col-md-6">
            <img
              src="@/images/home/27718-5-business-people-hd.png"
              alt
              style="margin-top: -100px"
              class="img-fluid"
              data-aos="fade-right"
              data-aos-once="false"
              data-aos-duration="1500"
              data-aos-delay="800"
            />
          </div>
          <div class="col-md-6 d-flex align-items-center">
            <div
              class="py-5 my-3 text-capitalize"
              data-aos="fade-left"
              data-aos-once="false"
              data-aos-duration="1500"
              data-aos-delay="800"
            >
              <div
                class="col-12"
                v-if="
                  getPageContentType('free_consultation_description') ==
                  'textarea'
                "
              >
                <div
                  v-html="getPageContent('free_consultation_description')"
                ></div>
              </div>
              <div
                class="col-12"
                v-else-if="
                  getPageContentType('free_consultation_description') == 'text'
                "
              >
                <p>
                  {{ getPageContent("free_consultation_description") ?? "-" }}
                </p>
              </div>
              <div v-else>
                <h4 class="display-4 fw-bolder text-white">
                  {{ __("Free online consultations") }}
                </h4>
                <p class="fs-2 fw-bolder text-white">
                  {{ __("Starting at $15/month") }}
                </p>
              </div>
              <span v-if="$page.props.auth">
                <a
                  v-if="$page.props.auth.logged_in_as == 'teacher'"
                  :href="route('pricing', { type: 'teacher' })"
                  class="btn btn-primary"
                  type="button"
                  >{{
                    getPageContent("free_consultation_button") ??
                    __("Get Membership")
                  }}</a
                >
                <a
                  v-if="$page.props.auth.logged_in_as == 'academy'"
                  :href="route('pricing', { type: 'academy' })"
                  class="btn btn-primary"
                  type="button"
                  >{{
                    getPageContent("free_consultation_button") ??
                    __("Get Membership")
                  }}</a
                >
              </span>
              <a
                v-else
                :href="route('register', { tab: 'teacher' })"
                class="btn btn-primary"
                type="button"
                >{{
                  getPageContent("free_consultation_button") ??
                  __("Get Membership")
                }}</a
              >
            </div>
          </div>
        </div>
      </div>
    </div> -->
        <!-- Free Online Consultation -->
        <!-- <featured-testimonials-section></featured-testimonials-section> -->

        <!-- App Section -->
        <div class="section p-6 student-app">
            <div class="container">
                <div class="row">
                    <div class="col-lg-6">
                        <div
                            class="d-flex justify-content-center flex-column h-100"
                            data-aos="fade-left"
                            data-aos-once="false"
                            data-aos-duration="1500"
                            data-aos-delay="800"
                        >
                            <div
                                v-if="
                                    getPageContentType(
                                        'app_section_description'
                                    ) == 'textarea'
                                "
                            >
                                <div
                                    v-html="
                                        getPageContent(
                                            'app_section_description'
                                        )
                                    "
                                ></div>
                            </div>
                            <div
                                v-else-if="
                                    getPageContentType(
                                        'app_section_description'
                                    ) == 'text'
                                "
                            >
                                <p>
                                    {{
                                        getPageContent(
                                            "app_section_description"
                                        ) ?? "-"
                                    }}
                                </p>
                            </div>
                            <div v-else>
                                <span class="fs-3">{{ __("Download") }}</span>
                                <h2 class="display-6">
                                    {{ __("Consultant App") }}
                                </h2>
                                <p>
                                    Our commitment to excellence extends to our
                                    commitment to integrity. We adhere to the
                                    highest ethical standards, treating each
                                    case with the utmost professionalism and
                                    confidentiality. We understand the gravity
                                    of the legal matters we handle, and we
                                    approach them with the respect and
                                    dedication they deserve.
                                </p>
                            </div>

                            <div
                                class="d-flex align-items-start flex-lg-row flex-column mt-3"
                            >
                                <a
                                    href="https://play.google.com/store/apps/details?id=com.tutor.ogoul.student"
                                    class="btn bg-white d-flex align-items-center rounded-pill me-3 mb-2 mb-md-0"
                                    style="height: 70px"
                                >
                                    <img
                                        src="https://your-aws-url/1747484980_Google-Play.png"
                                        alt="Google Play Store"
                                        style="
                                            height: 100%;
                                            object-fit: contain;
                                        "
                                    />
                                </a>

                                <a
                                    href="https://play.google.com/store/apps/details?id=com.tutor.ogoul.student"
                                    class="btn bg-white d-flex align-items-center rounded-pill"
                                    style="height: 70px"
                                >
                                    <img
                                        src="https://your-aws-url/1747466271_Apple-Store.png"
                                        alt="Apple App Store"
                                        style="
                                            height: 100%;
                                            object-fit: contain;
                                        "
                                    />
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-6 position-relative">
                            <div
                                class="mobile-img text-end mb-4 mb-lg-0"
                                data-aos="fade-right"
                                data-aos-once="false"
                                data-aos-duration="1500"
                                data-aos-delay="500"
                            >
                                <img
                                    src="@/images/home/mobileimg.png"
                                    width="400"
                                    alt
                                    class="img-fluid"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- App Section -->

        <!-- Faqs Section -->
        <div class="section bg-white p-6" v-if="faqs?.length > 0">
            <div class="container">
                <div class="row">
                    <div class="col-12 mb-5 text-center">
                        <div
                            v-if="
                                getPageContentType(
                                    'faqs_section_description'
                                ) == 'textarea'
                            "
                        >
                            <div
                                v-html="
                                    getPageContent('faqs_section_description')
                                "
                            ></div>
                        </div>
                        <div
                            v-else-if="
                                getPageContentType(
                                    'faqs_section_description'
                                ) == 'text'
                            "
                        >
                            <p>
                                {{
                                    getPageContent(
                                        "faqs_section_description"
                                    ) ?? "-"
                                }}
                            </p>
                        </div>
                        <div v-else>
                            <span class="fs-3">{{ __("Find some ") }}</span>
                            <h2 class="5">{{ __("Answer Questions") }}</h2>
                            <p>
                                When you choose Elite Legal Services, you are
                                choosing a trusted partner dedicated to your
                                success. Here are some frequently asked
                                questions by some users. Feel free to add your
                                questions
                            </p>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div
                            class="accordion"
                            id="accordionPanelsStayOpenExample"
                        >
                            <div
                                class="accordion-item rounded-4 mb-3 border-0 shadow"
                                v-for="item in faqs"
                                :key="item.id"
                            >
                                <h2
                                    class="accordion-header border-0"
                                    :id="`panelsStayOpen-headingOne${item.id}`"
                                >
                                    <button
                                        class="accordion-button rounded-4 overflow-hidden bg-white border border-primary collapsed"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        :data-bs-target="`#panelsStayOpen-collapseOne${item.id}`"
                                        aria-expanded="false"
                                        :aria-controls="`panelsStayOpen-collapseOne${item.id}`"
                                    >
                                        <div
                                            class="d-flex align-items-start align-items-md-center gap-2 px-0 px-md-3"
                                        >
                                            <i
                                                class="bi bi-question-octagon-fill text-primary mt-1 mt-md-0 fs-1"
                                            ></i>
                                            <span
                                                class="fw-bold fs-3 text-primary ms-md-2 ms-0"
                                                >{{ item.name }}</span
                                            >
                                        </div>
                                    </button>
                                </h2>
                                <div
                                    :id="`panelsStayOpen-collapseOne${item.id}`"
                                    class="accordion-collapse collapse"
                                    :aria-labelledby="`panelsStayOpen-headingOne${item.id}`"
                                >
                                    <div
                                        class="accordion-body text-secondary fw-bold fs-4 px-4"
                                    >
                                        <div v-html="item.description"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- <div class="row pt-4 justify-content-center">
          <div class="col-md-3 d-flex justify-content-center">
            <Link href="#" class="learn-more btn position-relative">
              <span class="circle" aria-hidden="true">
                <span class="icon arrow"></span>
              </span>
              <span class="button-text">{{
                getPageContent("general_view_more_btn_text") ?? __("view more")
              }}</span>
            </Link>
          </div>
        </div> -->
            </div>
        </div>
        <!-- Faqs Section -->
        <!-- <featured-tags-section class="bg-light"></featured-tags-section> -->

        <!-- Button trigger modal -->
        <Modal
            style="background-color: white !important"
            ref="modal"
            class="rounded-0"
            maxWidth="lg"
            :id="'categoryModal'"
        >
            <div class="modal-content p-md-4">
                <div class="modal-header border-0">
                    <h5
                        class="modal-title ms-2 text-primary fs-2 fw-bold"
                        id="categoryModalLabel"
                    >
                        {{ __("Search nearby tutors") }}
                    </h5>
                    <button
                        :id="id + 'close'"
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>
                </div>
                <div
                    class="modal-body mx-md-3 d-flex align-items-center flex-column pb-md-5"
                >
                    <select
                        v-model="form.category"
                        class="form-select form-select-lg mb-3"
                        aria-label="Large select example"
                    >
                        <option value="" disabled selected>
                            {{ __("Select Category") }}
                        </option>
                        <option
                            v-for="category in $page.props.dashboard_data
                                .teacher_categories"
                            :key="category.id"
                            :value="category.id"
                            :title="
                                category.description[$page.props.locale] ||
                                category.description.en
                            "
                        >
                            {{
                                category.name[$page.props.locale] ||
                                category.name.en
                            }}
                        </option>
                    </select>
                    <select
                        v-model="form.duration"
                        class="form-select form-select-lg mb-3"
                        aria-label="Large select example"
                    >
                        <option value="" disabled selected>
                            {{ __("Select Duration") }}
                        </option>
                        <option value="30">30 minutes</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        {{ __("Close") }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        data-bs-dismiss="modal"
                        :disabled="!form.category || !form.duration"
                        @click="searchNearbyTutors"
                    >
                        {{ __("Search") }}
                    </button>
                </div>
            </div>
        </Modal>
        <Modal
            style="background-color: white !important"
            ref="modal"
            class="rounded-0"
            maxWidth="lg"
            :id="'nearestModal'"
        >
            <div class="modal-content p-md-4">
                <div class="modal-header border-0">
                    <h1 class="modal-title fs-5" id="nearestModalLabel"></h1>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>
                </div>
                <div
                    class="modal-body mx-md-3 d-flex align-items-center flex-column gap-2 pb-md-5"
                >
                    <h2 id="teacher_status">{{ __(nearbyTutorStatus) }}</h2>
                    <h6>{{ __(nearbySocketStatus) }}</h6>
                    <SpinnerLoader
                        v-if="nearbyLoading"
                        style="width: 3rem; height: 3rem"
                    />
                    <div id="paymentSelection"
                      class="card bg-transparent border border-info border-1 p-3" style="display: none;"
                    >
                      <div class="card-body">
                        <div class="row">
                          <div class="col-12">
                            <span class="fw-bold fs-2 ps-3 py-3 text-primary">{{
                              __("Select Payment Methods")
                            }}</span>

                            <!-- <span class="fs-5">{{ teacher.first_name ?? "" }} {{ teacher.last_name ?? ""}}</span> -->
                          </div>
                          <div class="col-12 mt-4">
                            <Carousel

                              ref="carousel"
                              v-model="currentSlide"
                              :key="key"
                              :settings="settings"
                              :breakpoints="breakpoints_payment"
                            >
                              <Slide
                                v-for="(gateway, index) in gateways"
                                :key="index"
                              >
                                <div
                                  :class="{
                                    'border border-1 border-primary rounded-3':
                                      form.gateway == gateway.code,
                                  }"
                                  @click="this.form.gateway = gateway.code"
                                >
                                  <div
                                    class="d-flex align-content-center justify-content-around bg-white py-2 rounded-3 payment-icons"
                                  >
                                    <img
                                      class=""
                                      :src="gateway.image"
                                      :alt="gateway.name"
                                    />
                                  </div>
                                  <h6 class="fs-6 mt-2 text-center fw-bold">
                                    {{ gateway.name }}
                                  </h6>
                                </div>
                              </Slide>

                              <template #addons>
                                <Navigation />
                              </template>


                            </Carousel>

                            <hr />
                          </div>
                          <div
                            class="col-12 mt-2 mb-5 text-center align-content-center"
                            v-if="parseInt($page.props.settings.enable_wallet_system)"
                          >
                            <button
                             @click="{form.gateway = 'wallet'; addFundRequest()}"
                              class="btn rounded-3 text-primary fw-bold fs-4 btn-sm btn-white"
                            >
                              <span class="mx-2"
                                ><img
                                  src="@/images/icons/wallet.png"
                                  width="20"
                                  height="20"
                              /></span>
                              {{  __("complete your transaction using your wallet") }}
                            </button>
                        </div>

                          <div class="d-grid mb-2">
                            <button
                            @click="addFundRequest" :disabled="form.processing || !form.gateway"
                              class="btn btn-primary rounded-pill fw-bold fs-3"
                              data-bs-dismiss="modal"
                            >
                             {{ __("Pay Now") }}
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        {{ __("Close") }}
                    </button>
                </div>
            </div>
        </Modal>
    </app-layout>
</template>

<script>
import { defineComponent, ref } from "vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Carousel, Navigation, Pagination, Slide } from "vue3-carousel";
import Modal from "@/Components/Modal.vue";
import Navbar from "@/Layouts/AppIncludes/Navbar.vue";
import FindTeacherBar from "@/Components/Teachers/FindTeacherBar.vue";
import FindAcademyBar from "@/Components/Academies/FindAcademyBar.vue";
import FindEventBar from "@/Components/Events/FindEventBar.vue";
import SpotlightTeacherSection from "@/Components/Teachers/SpotlightTeacherSection.vue";
import HomeStatisticsBar from "@/Components/HomeStatisticsBar.vue";
import FeaturedTagsSection from "@/Components/Shared/FeaturedTagsSection.vue";
import FeaturedEventSection from "@/Components/Events/FeaturedEventSection.vue";
import TeachersTabsSection from "@/Components/Teachers/TeachersTabs.vue";
import FeaturedTestimonialsSection from "@/Components/Shared/FeaturedTestimonialsSection.vue";
import PopularCoursesSection from "@/Components/Shared/PopularCoursesSection.vue";
import HomeStaticCardsSection from "@/Components/HomeStaticCardsSection.vue";
import { Head, Link } from "@inertiajs/inertia-vue3";
import Section from "@/Components/Section.vue";
import axios from "axios";
import FeaturedAcademySection from "@/Components/Academies/FeaturedAcademySection.vue";
import SpinnerLoader from "@/Components/Shared/SpinnerLoader.vue";
import { socket, state } from "@/Libraries/socket";
import { Modal as BootstrapModal } from "bootstrap";

export default defineComponent({
    props: {
        id: {
            type: String,
            required: true,
        },
    },
    components: {
        Head,
        Link,
        Modal,
        AppLayout,
        Navbar,
        FindTeacherBar,
        FindAcademyBar,
        FindEventBar,
        HomeStatisticsBar,
        TeachersTabsSection,
        HomeStaticCardsSection,
        SpotlightTeacherSection,
        FeaturedTagsSection,
        FeaturedEventSection,
        FeaturedTestimonialsSection,
        FeaturedAcademySection,
        PopularCoursesSection,
        Carousel,
        Slide,
        Section,
        Pagination,
        Navigation,
        SpinnerLoader,
    },
    async created() {
        this.getFAQS();
        this.getServiceCategories();
        await this.initializeLocation();
        this.initializePaymentGateways();
        socket?.on("live_request_accepted", async (liveRequest) => {
            liveRequest = typeof liveRequest === "string" ? JSON.parse(liveRequest) : liveRequest;
            if (this.responseCheckInterval) {
                clearInterval(this.responseCheckInterval);
                this.nearbyTutorStatus = "Teacher Accepted your request";
                this.nearbySocketStatus = "Please select a payment gateway and complete the payment to start the live session";
            }
            this.liveAccepted = true;
            this.liveRequestId = liveRequest.id;
            this.nearbyLoading = false;
            
            // Update form with live appointment data
            this.form.teacher_id = liveRequest.teacher_id;
            this.form.student_id = liveRequest.student_id;
            this.form.live_request_id = liveRequest.id;
            this.form.description = `Live session for category: ${liveRequest.category_id}`;
            const now = new Date();
            const timezoneOffset = now.getTimezoneOffset() * 60000;
            this.form.date = new Date(now - timezoneOffset).toISOString();

            
            // Show payment selection
            document.getElementById('paymentSelection').style.display = 'block';
        });

        socket?.on("live_request_rejected", (liveRequest) => {
            liveRequest = typeof liveRequest === "string" ? JSON.parse(liveRequest) : liveRequest;
            this.liveAccepted = false;
            //remove first teacher from nearbyTutors
            this.nearbyTutors.shift();
            if (this.nearbyTutors.length === 0) {
                this.nearbyTutorStatus = "No tutor is available at the moment";
                this.nearbyLoading = false;
            }
            this.startTeacherResponseCheck();
        });

        socket?.on("teacher_countdown_sync_request", (payload) => {
            try {
                payload = typeof payload === "string" ? JSON.parse(payload) : payload;
            } catch (e) {}

            if (!payload) return;

            // Validate that we're currently offering to this teacher for this live request
            const activeTeacher = this.nearbyTutors?.[0];
            if (
                this.liveRequestId &&
                payload.live_request_id === this.liveRequestId &&
                activeTeacher &&
                payload.teacher_id === activeTeacher.id &&
                !this.liveAccepted &&
                this.currentOfferStartedAt
            ) {
                const elapsedSec = Math.floor((Date.now() - this.currentOfferStartedAt) / 1000);
                const remaining = Math.max(0, 15 - elapsedSec);
                socket?.emit("teacher_countdown_sync_response", {
                    live_request_id: this.liveRequestId,
                    teacher_id: activeTeacher.id,
                    remaining_seconds: remaining,
                });
            }
        });

        // socket?.on("live_payment_completed", (appointment) => {
        //     this.liveAccepted = false;
        //     this.$toast.success("Live appointment created successfully");
        //     //redirect to call
        // });
    },

    data() {
        return {
            // Payment gateway related data
            currentSlide: 0,
            key: 1,
            gateways: [],
            form: this.$inertia.form({
                category: "",
                gateway: null,
                teacher_id: null,
                student_id: null,
                appointment_type_id: 4, // Hardcoded as per requirement
                live_request_id: null,
                description: '',
                date: null,
                duration: 30,
            }),
            settings: {
                itemsToShow: 1,
                snapAlign: "start",
                autoplay: false,
            },
            breakpoints: {
                // 700px and up
                700: {
                    itemsToShow: 1,
                    snapAlign: "start",
                },
                // 1024 and up
                1024: {
                    itemsToShow: 1,
                    snapAlign: "start",
                },
            },
            // breakpoints for responsive carousel
            breakpoints_payment: {
                // 700px and up
                700: {
                    itemsToShow: 2,
                    snapAlign: "start",
                },
                // 1024 and up
                1024: {
                    itemsToShow: 2,
                    snapAlign: "start",
                },
                1240: {
                    itemsToShow: 4,
                    snapAlign: "start",
                },
            },
            faqs: [],
            serviceCategories: [],
            userLocation: null,
            locationError: null,
            isLoadingLocation: false,
            responseCheckInterval: null,
            nearbyTutors: [],
            nearbyTutorStatus: "",
            nearbySocketStatus: "",
            liveAccepted: false,
            nearbyLoading: false,
            liveRequestId: null,
            currentOfferStartedAt: null,
        };
    },
    mounted() {
        const myModalEl = document.getElementById('nearestModal')
        
        myModalEl.addEventListener('show.bs.modal', event => {
            document.getElementById('paymentSelection').style.display = 'none';
        });
        myModalEl.addEventListener('hidden.bs.modal', event => {
            this.nearbyTutors = [];
            this.nearbyLoading = false;
            this.nearbyTutorStatus = "";
            this.nearbySocketStatus = "";
            document.getElementById('paymentSelection').style.display = 'none';
        })
    },
    methods: {
        // Payment gateway methods
        addFundRequest() {
            if (this.liveRequestId) {
                // This is a live appointment booking
                this.form.post(this.route("students.book_live_appointment"), {
                    onSuccess: (resp) => {
                        if (this.form.gateway === "wallet") {
                            this.$toast.success("Live appointment created successfully");
                            // Handle wallet payment success
                            // Close the nearestModal popup if open
                            try {
                                const modalEl = document.getElementById('nearestModal');
                                if (modalEl) {
                                    if (window.bootstrap && window.bootstrap.Modal) {
                                        const instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                                        instance.hide();
                                    } else if (window.$ && typeof window.$(modalEl).modal === 'function') {
                                        // Fallback for jQuery-based Bootstrap
                                        window.$(modalEl).modal('hide');
                                    } else {
                                        // As a last resort, toggle aria-hidden/display
                                        modalEl.setAttribute('aria-hidden', 'true');
                                        modalEl.style.display = 'none';
                                    }
                                }
                            } catch (e) { /* ignore */ }
                            // this.$inertia.visit(route('appointment_log'));
                        } else {
                            // Redirect to payment gateway
                            window.location.replace(
                                this.route("user.addFund.confirm", {
                                    transaction: resp.props.response_data.fund.transaction,
                                })
                            );
                        }
                    },
                });
            } else {
                // This is a regular appointment booking
                this.form.post(this.route("students.book_appointment"), {
                    onSuccess: (resp) => {
                        if (this.form.gateway === "wallet") {
                            // Handle wallet payment success
                            this.$inertia.visit(route('payment.success'));
                        } else {
                            // Redirect to payment gateway
                            window.location.replace(
                                this.route("user.addFund.confirm", {
                                    transaction: resp.props.response_data.fund.transaction,
                                })
                            );
                        }
                    },
                });
            }
        },
        
        // Initialize payment gateways
        initializePaymentGateways() {
            axios.get("/gateways").then((res) => {
                this.gateways = res.data.filter(g => g.id !== 1);
            });
        },
        async initializeLocation() {
            this.isLoadingLocation = true;
            try {
                this.userLocation = await this.getCurrentLocation();
                this.locationError = null;
            } catch (error) {
                console.error("Location initialization error:", error);
                this.locationError = error.message;
                this.nearbyTutorStatus = error.message;
            } finally {
                this.isLoadingLocation = false;
            }
        },

        getCurrentLocation() {
            return new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    reject(
                        new Error(
                            "Geolocation is not supported by your browser"
                        )
                    );
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const location = {
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                        };
                        resolve(location);
                    },
                    (error) => {
                        let errorMessage = "Unable to retrieve your location";
                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage =
                                    "Location permission denied. Please enable location services to find nearby tutors.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage =
                                    "Location information is unavailable.";
                                break;
                            case error.TIMEOUT:
                                errorMessage =
                                    "The request to get your location timed out.";
                                break;
                        }
                        reject(new Error(errorMessage));
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0,
                    }
                );
            });
        },
        readFunction() {
            var dots = document.getElementById("dots");
            var moreText = document.getElementById("more");
            var btnText = document.getElementById("myBtn");

            if (dots.style.display === "none") {
                dots.style.display = "inline";
                btnText.innerHTML = "read more";
                moreText.style.display = "none";
            } else {
                dots.style.display = "none";
                btnText.innerHTML = "read less";
                moreText.style.display = "inline";
            }
        },
        getFAQS() {
            axios.get(this.route("getApiFaqs")).then((res) => {
                this.faqs = res.data.data;
            });
        },
        getServiceCategories() {
            axios.get(this.route("getApiServiceCategories")).then((res) => {
                console.log(res.data.data);
                this.serviceCategories = res.data.data;
            });
        },

        launchModal() {
            const modalButton = document.querySelector(
                '[data-bs-target="#newsletterModal"]'
            );
            if (modalButton) {
                modalButton.click();
            }
        },
        showNearByTutorsModal() {
            const myModal = new BootstrapModal("#nearestModal", {
                keyboard: false,
            });
            myModal.show();
        },

        async searchNearbyTutors() {
            this.nearbyLoading = true;
            if (this.isLoadingLocation) {
                this.nearbyTutorStatus =
                    "Please wait while we get your location...";
                this.showNearByTutorsModal();
                return;
            }

            if (this.locationError) {
                this.nearbyTutorStatus = this.locationError;
                this.showNearByTutorsModal();
                this.nearbyLoading = false;
                return;
            }

            if (!this.userLocation) {
                try {
                    this.nearbyTutorStatus = "Getting your location...";
                    this.showNearByTutorsModal();
                    await this.initializeLocation();

                    // If we still don't have location after trying to initialize
                    if (!this.userLocation) {
                        this.nearbyLoading = false;
                        return;
                    }
                } catch (error) {
                    this.locationError = error.message;
                    this.nearbyTutorStatus = error.message;
                    this.showNearByTutorsModal();
                    this.nearbyLoading = false;
                    return;
                }
            }

            if(!this.form.category || !this.form.duration){
                this.$toast.error("Please select category and duration");
                return;
            }

            try {
                this.nearbyTutorStatus = "Searching for nearby tutors...";
                this.showNearByTutorsModal();

                // const now = new Date();
                // const timezoneOffset = now.getTimezoneOffset() * 60000;
                // const currentTime = new Date(now - timezoneOffset).toISOString();

                const response = await axios.get(
                    this.route("teacher.search.radius"),
                    {
                        params: {
                            latitude: this.userLocation.latitude,
                            longitude: this.userLocation.longitude,
                            radius: 10, // Default 10km radius
                            unit: "km", // Default unit kilometers,
                            category: this.form.category,
                            duration: this.form.duration,
                            current_time: new Date().toISOString(),
                        },
                    }
                );

                if (response.data.status) {
                    this.nearbyTutors = response.data.data;
                    this.liveRequestId = response.data.live_request_id;
                    if (this.nearbyTutors?.length === 0) {
                        this.nearbyTutorStatus = "No tutors found in your area";
                        this.nearbyLoading = false;
                    } else {
                        this.nearbyTutorStatus = `${
                            this.nearbyTutors?.length
                        } tutor${
                            this.nearbyTutors?.length === 1 ? "" : "s"
                        } found in your area`;
                        this.nearbySocketStatus =
                            "Waiting for tutor response...";
                        // Start checking for teacher responses
                        this.startTeacherResponseCheck();
                    }
                } else {
                    console.error(
                        "Error fetching nearby tutors:",
                        response.data.message
                    );
                    this.nearbyTutorStatus =
                        "Error: " +
                        (response.data.message || "Failed to load tutors");
                    this.nearbyLoading = false;
                }
            } catch (error) {
                console.error("API Error:", error);
                this.nearbyTutorStatus =
                    "Error: Could not connect to the server";
                this.nearbyLoading = false;
            }
        },
        async startTeacherResponseCheck() {
            // Clear any existing interval
            if (this.responseCheckInterval) {
                clearInterval(this.responseCheckInterval);
            }

            // Start a new interval
            this.currentOfferStartedAt = Date.now();
            this.responseCheckInterval = setInterval(async () => {
                // If live is already accepted, clear the interval
                if (this.liveAccepted) {
                    clearInterval(this.responseCheckInterval);
                    return;
                }

                // If no more teachers, clear the interval and update status
                if (this.nearbyTutors.length === 0) {
                    clearInterval(this.responseCheckInterval);
                    this.nearbyTutorStatus =
                        "None of the available teachers responded";
                    this.nearbySocketStatus = "";
                    this.nearbyLoading = false;
                    return;
                }

                // Get the first teacher's ID
                const firstTeacher = this.nearbyTutors[0];

                // Reject the first teacher
                try {
                    await axios.post("/reject_live_appointment", {
                        teacher_id: firstTeacher.id,
                    });

                    console.log("Request rejected successfully");
                    // Remove the rejected teacher from the array
                    this.nearbyTutors = this.nearbyTutors.filter(
                        (teacher) => teacher.id !== firstTeacher.id
                    );

                    if (this.nearbyTutors.length > 0) {
                        this.nearbySocketStatus = `Waiting for next tutor to respond (${this.nearbyTutors.length} remaining)`;
                        // Reset the window start for the next teacher
                        this.currentOfferStartedAt = Date.now();
                    } else {
                        this.nearbyTutorStatus =
                            "None of the available teachers responded";
                        this.nearbySocketStatus = "";
                        this.nearbyLoading = false;
                    }
                } catch (error) {
                    console.error("Error rejecting teacher:", error);
                    clearInterval(this.responseCheckInterval);
                    this.nearbySocketStatus = "Error processing tutor response";
                }
            }, 15000); // 15 seconds
        },
    },
});
</script>
<style>
#more {
    display: none;
}

.learn-more {
    width: 11rem;
}
</style>
