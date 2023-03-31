<template>
    <div class="pb-5">
        <label v-if="!!label" class="v-label v-label--active theme--light" style="font-size: 12px;">{{label}}</label>
        <div ref="calendar"></div>
        <div class="pt-5 pl-5">
            <!--suppress HtmlUnknownTag -->
            <VMessages :value="errorBucket" color="error"/>
        </div>
    </div>
</template>

<script>
import FortusCalendar from 'fortus-calendar'
import 'fortus-calendar/dist/css/fortus-calendar.css'
import {VInput} from "vuetify/lib/components";
export default {
    name: "FortusCalendar",
    extends: VInput,
    props: {
        label: String|null,
        value: {type: Date|Object}, //Значение по умолчанию Для периода передается либо конечная дата, либо объект {from, to}
        type: {type: 'date'|'datetime'|'date-period'|'datetime-period'}, // Тип календаря items: ['date', 'datetime', 'date-period', 'datetime-period']},
        drops: {type: String}, // Горизонтальное направление, items: ['down', 'up']},
        opens: {type: String}, // Вертикальное направление, items: ['left', 'right']},
        noDefault: {type: Boolean}, // Не выставлять дату по умолчанию (служебный параметр)
        autoApply: {type: Boolean}, // Авто применение
        minDate: {type: Date}, // Минимальная дата
        maxDate: {type: Date}, // Максимальная дата
        periodType: {type: Array|String}, //  Периоды, items: ['years', 'year', 'quarter', 'half', 'month', 'day', 'week', 'timeOfDay']},
        maxPeriod: {type: Number}, // Максимальный интервал периода в ЧАСАХ
        disabled: {type: Boolean},
    },
    data: () => ({
        localValue: null,
        calendar: null,
        watch: true,
    }),
    computed: {
        propsWatch(){
            return JSON.stringify([
                this.type, this.drops, this.opens, this.noDefault, this.autoApply, this.minDate, this.maxDate, this.periodType, this.maxPeriod
            ])
        },
        currentDate: {
            get(){
                return this.localValue ?? this.value
            },
            set(val){
                if(val){
                    this.localValue = val
                }
                return this.$emit('input', val)
            }
        }
    },
    watch: {
        propsWatch(){
            this.mountCalendar()
        },
        value(){
            if(this.value) this.mountCalendar()
        }
    },
    mounted() {
        this.mountCalendar()
    },
    methods: {
        mountCalendar(){
            this.calendar = new FortusCalendar({
                el: this.$refs.calendar,
                ...this.$props,
                currentDate: this.currentDate ?? null
            })
            if(!this.currentDate) this.currentDate = this.calendar.value
            this.calendar.addEventListener('apply', (e) => {
                this.currentDate = e.detail
            })
            this.calendar.addEventListener('invalid', () => {
                this.currentDate = null
            })
        }
    }
}
</script>

<style scoped>

</style>
