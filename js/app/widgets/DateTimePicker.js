import React from 'react'
import { render } from 'react-dom'
import moment from 'moment'

import Form from 'antd/lib/form'
const FormItem = Form.Item

import Button from 'antd/lib/button'
import DatePicker from 'antd/lib/date-picker'
import TimePicker from 'antd/lib/time-picker'
import LocaleProvider from 'antd/lib/locale-provider'
import frBE from 'antd/lib/locale-provider/fr_BE'

const today = moment().startOf('day')

const dateFormat = 'DD/MM/YYYY';
const timeFormat = 'HH:mm';

let minutes = [];
for (let i = 0; i <= 60; i++) {
  if (0 !== i % 15) {
    minutes.push(i)
  }
}

class DateTimePicker extends React.Component {

  constructor(props) {
    super(props);
    this.state = {
      value: this.props.defaultValue,
    };
  }

  componentDidMount() {
    this.props.onChange(this.state.value)
  }

  onDateChange(date, dateString) {

    let { value } = this.state;

    if (!value) {
      value = moment()
    }

    value.set('date', date.get('date'));
    value.set('month', date.get('month'));
    value.set('year', date.get('year'));

    this.setState({ value })

    this.props.onChange(value)
  }

  onTimeChange(date, timeString) {

    let { value } = this.state

    if (!value) {
      value = moment()
    }

    value.set('hour', date.get('hour'))
    value.set('minute', date.get('minute'))
    value.set('second', 0)

    this.setState({ value })

    this.props.onChange(value)
  }

  disabledDate(date) {
    if (date) {
      return date.isBefore(today)
    }
  }

  disabledMinutes(h) {
    return minutes
  }

  render() {

    const formItemProps = this.props.error ? {
      validateStatus: "error",
    } : {}

    let datePickerProps = {}
    if (this.props.hasOwnProperty('getDatePickerContainer') && typeof this.props.getDatePickerContainer === 'function') {
      datePickerProps = {
        getCalendarContainer: this.props.getDatePickerContainer
      }
    }

    let timePickerProps = {}
    if (this.props.hasOwnProperty('getTimePickerContainer') && typeof this.props.getTimePickerContainer === 'function') {
      timePickerProps = {
        getPopupContainer: this.props.getTimePickerContainer
      }
    }

    return (
      <div>
        <FormItem {...formItemProps}>
          <LocaleProvider locale={frBE}>
            <DatePicker
              disabledDate={this.disabledDate}
              onChange={this.onDateChange.bind(this)}
              format={dateFormat}
              placeholder="Date"
              defaultValue={this.props.defaultValue}
              { ...datePickerProps }
            />
          </LocaleProvider>
          <LocaleProvider locale={frBE}>
            <TimePicker
              disabledMinutes={this.disabledMinutes}
              onChange={this.onTimeChange.bind(this)}
              defaultValue={this.props.defaultValue}
              format={timeFormat}
              hideDisabledOptions
              placeholder="Heure"
              addon={panel => (
                <Button size="small" type="primary" onClick={() => panel.close()}>OK</Button>
              )}
              { ...timePickerProps }
            />
          </LocaleProvider>
        </FormItem>
      </div>
    )
  }
}

export default function(el, options) {

  const defaultProps = {
    getDatePickerContainer: null,
    getTimePickerContainer: null,
    onChange: () => {}
  }

  if (null !== options.defaultValue) {
    options.defaultValue = moment(options.defaultValue)
  } else {
    delete options.defaultValue
  }

  const props = { ...defaultProps, ...options }

  render(<DateTimePicker { ...props } />, el)
}
